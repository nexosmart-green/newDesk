<?php

namespace App\Domain\StatusPage\Import;

use App\Domain\Clients\Actions\SaveClientUser;
use App\Domain\Clients\Models\Client;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Import\LegacySecretBox;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Queries\ProjectLogo;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Copies monitoreo's clients, users and status pages (RF-13.12, ADR-0002 §9). Reads the legacy
 * database and never writes it (CA-13.23). Clients, users and portal_projects become newDesk rows
 * recorded in `import_mappings`, so a second run updates the same rows; components and their history
 * keep monitoreo's ids. Team users are matched by email; the ones newDesk does not have are created
 * invited (no password) and reported.
 */
final class StatusPageImporter
{
    private const SOURCE = 'monitoreo';

    /** Children first. */
    private const TABLES = ['status_pagespeed_runs', 'status_incident_notes', 'status_project_folders', 'status_periods',
        'status_checks_hourly', 'status_checks', 'status_components'];

    private const COMPONENT = ['kind', 'label', 'parts', 'position', 'url', 'server_id', 'target', 'target_owner', 'max_age_hours', 'slow_ms',
        'status', 'status_since', 'phrase', 'detail', 'bad_streak', 'bad_since', 'down_since', 'last_checked_at', 'last_ok_at', 'latency_ms',
        'status_code', 'ssl_expires_at', 'created_at', 'updated_at'];

    /** @var list<string> team users created as invited on this run */
    public array $invited = [];

    private ConnectionInterface $legacy;

    private int $companyId;

    /** @param  string  $connection  the legacy database (`monitoreo_legacy`; tests pass their own) */
    public function __construct(
        private readonly LegacySecretBox $box,
        private readonly string $connection = 'monitoreo_legacy',
    ) {}

    /** --fresh: only the status page history; clients, users and projects stay mapped and are updated. */
    public function wipe(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->delete();
        }
    }

    /** @return array<string, int> rows copied per legacy table */
    public function import(int $companyId): array
    {
        $this->legacy = DB::connection($this->connection);
        $this->companyId = $companyId;
        $this->invited = [];
        $roles = Role::query()->forCompany($companyId)->pluck('id', 'name');

        $teamUsers = $this->teamUsers($roles);
        $clients = $this->clients();
        $clientUsers = $this->clientUsers($clients, (int) $roles[DefaultRoles::CLIENT]);
        $projects = $this->projects($clients);
        $this->developers($projects, $teamUsers);

        $project = fn (object $row) => ['project_id' => $projects[$row->project_id]];

        return [
            'team_users' => count($teamUsers),
            'clients' => count($clients),
            'client_users' => $clientUsers,
            'portal_projects' => count($projects),
            'portal_components' => $this->copy('portal_components', 'status_components', ['id'], [...self::COMPONENT, 'project_id'], $project),
            'portal_checks' => $this->copy('portal_checks', 'status_checks', ['id'], ['component_id', 'checked_at', 'ok', 'status_code', 'latency_ms']),
            'portal_checks_hourly' => $this->copy('portal_checks_hourly', 'status_checks_hourly', ['component_id', 'hour'], ['samples', 'failures', 'avg_ms', 'max_ms']),
            'portal_periods' => $this->copy('portal_periods', 'status_periods', ['id'], ['component_id', 'status', 'started_at', 'ended_at', 'detail']),
            'portal_incident_notes' => $this->copy('portal_incident_notes', 'status_incident_notes', ['id'], ['period_id', 'user_id', 'author', 'body', 'created_at'],
                fn (object $row) => ['user_id' => $row->user_id === null ? null : ($teamUsers[$row->user_id] ?? null)]),
            'portal_project_folders' => $this->copy('portal_project_folders', 'status_project_folders', ['id'], ['project_id', 'server_id', 'path', 'created_at'], $project),
            'portal_pagespeed' => $this->copy('portal_pagespeed', 'status_pagespeed_runs', ['id'],
                ['component_id', 'strategy', 'score', 'lcp_ms', 'cls', 'inp_ms', 'fcp_ms', 'tbt_ms', 'field', 'error', 'measured_at']),
        ];
    }

    /**
     * monitoreo's team: matched by lowercase email, else created invited, Admin or Desarrollador.
     *
     * @param  Collection<string, int>  $roles
     * @return array<int, int> legacy id → newDesk id
     */
    private function teamUsers($roles): array
    {
        $map = [];
        foreach ($this->legacy->table('users')->whereNull('client_id')->orderBy('id')->get() as $row) {
            $email = mb_strtolower(trim($row->email));
            $user = $this->target('users', $row->id, User::class)
                ?? User::query()->where('company_id', $this->companyId)->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($user === null) {
                $user = User::query()->create([
                    'company_id' => $this->companyId,
                    'role_id' => $roles[$row->is_admin ? DefaultRoles::ADMIN : DefaultRoles::DEVELOPER],
                    ...SaveClientUser::namesFrom($email),
                    'email' => $email,
                    'password' => null,
                    'status' => UserStatus::Invited,
                ]);
                $this->invited[] = $email;
            }
            if ($row->discord_user_id !== null) {
                $user->forceFill(['discord_user_id' => $row->discord_user_id])->save();
            }
            $map[$row->id] = $this->remember('users', $row->id, $user->id);
        }

        return $map;
    }

    /** @return array<int, int> legacy id → newDesk id */
    private function clients(): array
    {
        $map = [];
        foreach ($this->legacy->table('clients')->orderBy('id')->get() as $row) {
            $client = $this->target('clients', $row->id, Client::class)
                ?? Client::query()->forCompany($this->companyId)->where('name', $row->name)->first()
                ?? new Client(['company_id' => $this->companyId]);
            $client->timestamps = false;
            $client->forceFill(['company_id' => $this->companyId, 'name' => $row->name, 'active' => (bool) $row->active,
                'created_at' => $row->created_at, 'updated_at' => $row->updated_at])->save();
            $map[$row->id] = $this->remember('clients', $row->id, $client->id);
        }

        return $map;
    }

    /** Portal users: the "Cliente" role, the address lowercased (the portal login matches it byte for byte, D-076). */
    private function clientUsers(array $clients, int $roleId): int
    {
        $rows = $this->legacy->table('users')->whereNotNull('client_id')->orderBy('id')->get();
        foreach ($rows as $row) {
            $email = mb_strtolower(trim($row->email));
            $user = $this->target('users', $row->id, User::class)
                ?? User::query()->where('company_id', $this->companyId)->whereNotNull('client_id')->where('email', $email)->first()
                ?? new User(['company_id' => $this->companyId, ...SaveClientUser::namesFrom($email)]);
            $user->forceFill(['company_id' => $this->companyId, 'role_id' => $roleId, 'client_id' => $clients[$row->client_id], 'email' => $email,
                'status' => $row->active ? UserStatus::Active : UserStatus::Inactive, 'last_login_at' => $row->last_login_at])->save();
            $this->remember('users', $row->id, $user->id);
        }

        return $rows->count();
    }

    /** portal_projects → projects, created as new ones the first time (code from the slug). @return array<int, int> */
    private function projects(array $clients): array
    {
        $map = [];
        foreach ($this->legacy->table('portal_projects')->orderBy('id')->get() as $row) {
            $project = $this->target('portal_projects', $row->id, Project::class)
                ?? new Project(['company_id' => $this->companyId, 'code_prefix' => $this->codePrefix($row->slug), 'status' => ProjectStatus::Active]);
            $project->timestamps = false;
            $project->forceFill([
                'client_id' => $clients[$row->client_id], 'name' => $row->name, 'slug' => $row->slug, 'primary_color' => $row->primary_color,
                'domain' => $row->domain, 'domain_expires_at' => $row->domain_expires_at, 'domain_expires_manual' => $row->domain_expires_manual,
                'domain_checked_at' => $row->domain_checked_at, 'domain_notice_level' => $row->domain_notice_level,
                'errors_url' => $row->errors_url, 'discord_channel_id' => $row->discord_channel_id, 'portal_active' => (bool) $row->active,
                'errors_token' => $row->errors_token_enc === null ? null
                    : $this->box->decrypt($row->errors_token_enc, LegacySecretBox::row('portal_projects.errors_token_enc', $row->id)),
                'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
            ])->save();
            $this->logo($project, $row);
            $map[$row->id] = $this->remember('portal_projects', $row->id, $project->id);
        }

        return $map;
    }

    /** The BLOB becomes a file on the private disk. */
    private function logo(Project $project, object $row): void
    {
        $path = ProjectLogo::path($project);
        if ($row->logo === null) {
            Storage::disk('local')->delete($path);
        } else {
            Storage::disk('local')->put($path, $row->logo);
        }
        $project->forceFill($row->logo === null
            ? ['logo_path' => null, 'logo_type' => null, 'logo_updated_at' => null]
            : ['logo_path' => $path, 'logo_type' => $row->logo_type, 'logo_updated_at' => $row->logo_updated_at])->save();
    }

    /** The lead is the responsible; lead and secondaries are the members (ADR-0002 §6). */
    private function developers(array $projects, array $teamUsers): void
    {
        $developers = $this->legacy->table('portal_project_developers')->get()->groupBy('project_id');
        foreach ($projects as $legacyId => $projectId) {
            $rows = $developers[$legacyId] ?? collect();
            $lead = $rows->firstWhere('role', 'lead');
            $project = Project::query()->forCompany($this->companyId)->findOrFail($projectId);
            $project->timestamps = false;
            $project->forceFill(['leader_user_id' => $lead === null ? null : ($teamUsers[$lead->user_id] ?? null)])->save();
            $project->members()->sync($rows->map(fn (object $row) => $teamUsers[$row->user_id] ?? null)->filter()->unique()->values()->all());
        }
    }

    /**
     * A legacy table copied with its keys, in chunks; $override maps a row to its translated columns.
     *
     * @param  list<string>  $keys
     * @param  list<string>  $columns
     */
    private function copy(string $from, string $to, array $keys, array $columns, ?\Closure $override = null): int
    {
        $count = 0;
        $write = function ($rows) use ($to, $keys, $columns, $override, &$count) {
            DB::table($to)->upsert(
                $rows->map(fn (object $row) => ['company_id' => $this->companyId, ...(array) $row, ...($override ? $override($row) : [])])->all(),
                $keys,
                ['company_id', ...$columns],
            );
            $count += $rows->count();
        };
        $query = $this->legacy->table($from);
        if ($keys === ['id']) {
            $query->chunkById(2000, $write);
        } else {
            $query->orderBy($keys[0])->orderBy($keys[1])->chunk(2000, $write);
        }

        return $count;
    }

    /** "playinn-cafe" → playinncafe: lowercase letters and digits, 2 to 12, unique in the company (CA-03.01). */
    private function codePrefix(string $slug): string
    {
        $base = substr(str_pad((string) preg_replace('/[^a-z0-9]/', '', strtolower($slug)), 2, '0'), 0, 12);
        for ($n = 1; ; $n++) {
            $candidate = $n === 1 ? $base : substr($base, 0, 12 - strlen((string) $n)).$n;
            if (! Project::query()->forCompany($this->companyId)->where('code_prefix', $candidate)->exists()) {
                return $candidate;
            }
        }
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $model
     * @return T|null
     */
    private function target(string $table, int $legacyId, string $model)
    {
        $id = DB::table('import_mappings')->where(['source' => self::SOURCE, 'source_table' => $table, 'source_id' => $legacyId])->value('target_id');

        return $id === null ? null : $model::query()->withoutGlobalScopes()->find($id);
    }

    private function remember(string $table, int $legacyId, int $targetId): int
    {
        DB::table('import_mappings')->upsert(
            ['source' => self::SOURCE, 'source_table' => $table, 'source_id' => $legacyId, 'target_type' => $table === 'portal_projects' ? 'project' : rtrim($table, 's'), 'target_id' => $targetId],
            ['source', 'source_table', 'source_id'],
            ['target_type', 'target_id'],
        );

        return $targetId;
    }
}
