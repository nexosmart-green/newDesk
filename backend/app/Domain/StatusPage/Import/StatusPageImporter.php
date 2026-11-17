<?php

namespace App\Domain\StatusPage\Import;

use App\Domain\Clients\Actions\SaveClientUser;
use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Import\LegacySecretBox;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Enums\ProjectType;
use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Queries\ProjectLogo;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Copies monitoreo's clients, users and status pages (RF-13.12, ADR-0002 §9). Reads the legacy
 * database and never writes it (CA-13.23). Clients and projects loaded by hand in newDesk first are
 * reused (see projects() and --dry-run's pairs()); the rest are created. Everything is recorded in
 * `import_mappings`, so a second run lands on the same rows; components and their history keep
 * monitoreo's ids. Team users are matched by email; the ones newDesk does not have are created
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

    /**
     * @param  string  $connection  the legacy database (`monitoreo_legacy`; tests pass their own)
     * @param  array<string, string>  $links  --link: monitoreo slug → code of the newDesk project it is
     */
    public function __construct(
        private readonly LegacySecretBox $box,
        private readonly string $connection = 'monitoreo_legacy',
        private readonly array $links = [],
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
        [$projects, $created] = $this->projects($clients);
        $this->developers($created, $teamUsers);

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

    /**
     * --dry-run: how each portal_project would be paired with a newDesk project, writing nothing.
     *
     * @return list<array{slug: string, name: string, client: string, code: string|null}> code null = it would be created
     */
    public function pairs(int $companyId): array
    {
        $this->legacy = DB::connection($this->connection);
        $this->companyId = $companyId;
        $clients = $this->legacy->table('clients')->get()->keyBy('id');

        return $this->legacy->table('portal_projects')->orderBy('id')->get()->map(function (object $row) use ($clients) {
            $project = $this->findProject($row, $this->findClient($clients[$row->client_id])?->id);
            $this->ensureSlugFree($row->slug, $project ?? new Project);

            return ['slug' => $row->slug, 'name' => $row->name, 'client' => $clients[$row->client_id]->name, 'code' => $project?->code_prefix];
        })->all();
    }

    /** A client newDesk already has (loaded by hand, RF-02) is reused as it is: same legal or trade name. @return array<int, int> legacy id → newDesk id */
    private function clients(): array
    {
        $map = [];
        foreach ($this->legacy->table('clients')->orderBy('id')->get() as $row) {
            $client = $this->findClient($row);
            if ($client === null) {
                $client = new Client(['company_id' => $this->companyId]);
                $client->timestamps = false;
                $client->forceFill(['legal_name' => $row->name, 'status' => $row->active ? ClientStatus::Active : ClientStatus::Inactive,
                    'created_at' => $row->created_at, 'updated_at' => $row->updated_at])->save();
            }
            $map[$row->id] = $this->remember('clients', $row->id, $client->id);
        }

        return $map;
    }

    private function findClient(object $row): ?Client
    {
        return $this->target('clients', $row->id, Client::class)
            ?? Client::query()->forCompany($this->companyId)
                ->where(fn ($query) => $query->where('legal_name', $row->name)->orWhere('trade_name', $row->name))->first();
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

    /**
     * portal_projects → projects. One newDesk already has (paired by --link, a previous run, or the same name
     * in the same client) only takes the status page fields: its name, code, type, leader and members are
     * newDesk's. The others are created as hour-bag projects (code from the slug), with their developers.
     *
     * @param  array<int, int>  $clients
     * @return array{0: array<int, int>, 1: array<int, int>} every legacy id → newDesk id, and the created ones
     */
    private function projects(array $clients): array
    {
        $map = [];
        $created = [];
        foreach ($this->legacy->table('portal_projects')->orderBy('id')->get() as $row) {
            $project = $this->findProject($row, $clients[$row->client_id]);
            $isNew = $project === null;
            $project ??= new Project(['company_id' => $this->companyId, 'client_id' => $clients[$row->client_id], 'name' => $row->name,
                'code_prefix' => $this->codePrefix($row->slug), 'type' => ProjectType::Bag, 'status' => ProjectStatus::Active]);
            $this->ensureSlugFree($row->slug, $project);
            $project->timestamps = false;
            $project->forceFill([
                'slug' => $row->slug, 'primary_color' => $row->primary_color,
                'domain' => $row->domain, 'domain_expires_at' => $row->domain_expires_at, 'domain_expires_manual' => $row->domain_expires_manual,
                'domain_checked_at' => $row->domain_checked_at, 'domain_notice_level' => $row->domain_notice_level,
                'errors_url' => $row->errors_url, 'discord_channel_id' => $row->discord_channel_id, 'portal_active' => (bool) $row->active,
                'errors_token' => $row->errors_token_enc === null ? null
                    : $this->box->decrypt($row->errors_token_enc, LegacySecretBox::row('portal_projects.errors_token_enc', $row->id)),
                ...($isNew ? ['created_at' => $row->created_at, 'updated_at' => $row->updated_at] : []),
            ])->save();
            $this->logo($project, $row);
            $map[$row->id] = $this->remember('portal_projects', $row->id, $project->id);
            if ($isNew) {
                $created[$row->id] = $project->id;
            }
        }

        return [$map, $created];
    }

    /** --link=<slug>:<code> wins (to fix a wrong pair), then a previous run, then the same name in the same client. */
    private function findProject(object $row, ?int $clientId): ?Project
    {
        if (isset($this->links[$row->slug])) {
            $code = mb_strtolower($this->links[$row->slug]);

            return Project::query()->forCompany($this->companyId)->where('code_prefix', $code)->first()
                ?? throw new \RuntimeException("--link={$row->slug}:{$code}: no hay un proyecto con el código {$code}.");
        }

        return $this->target('portal_projects', $row->id, Project::class)
            ?? ($clientId === null ? null : Project::query()->forCompany($this->companyId)
                ->where('client_id', $clientId)->where('name', $row->name)->first());
    }

    /** Old portal URLs keep working only with monitoreo's slug: another project holding it is a pair to fix. */
    private function ensureSlugFree(string $slug, Project $project): void
    {
        $holder = Project::query()->withTrashed()->forCompany($this->companyId)->where('slug', $slug)
            ->when($project->exists, fn ($query) => $query->whereKeyNot($project->id))->value('code_prefix');
        if ($holder !== null) {
            throw new \RuntimeException("El identificador {$slug} ya lo usa el proyecto {$holder}: si es el mismo, vinculalo con --link={$slug}:{$holder}.");
        }
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

    /** "playinn-cafe" → playinncaf: a letter, then letters and digits, 2 to 10, unique in the company (CA-03.01). */
    private function codePrefix(string $slug): string
    {
        $clean = (string) preg_replace('/[^a-z0-9]/', '', strtolower($slug));
        $base = substr(str_pad(ctype_alpha($clean[0] ?? '') ? $clean : 'p'.$clean, 2, '0'), 0, 10);
        for ($n = 1; ; $n++) {
            $candidate = $n === 1 ? $base : substr($base, 0, 10 - strlen((string) $n)).$n;
            if (! Project::query()->withTrashed()->forCompany($this->companyId)->where('code_prefix', $candidate)->exists()) {
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
