<?php

namespace App\Domain\Monitoring\Discord;

use App\Domain\Monitoring\Alerts\AlertRules;
use App\Domain\Monitoring\Models\ServerSnapshot;
use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Models\Component;
use App\Domain\StatusPage\Models\ProjectFolder;
use App\Domain\StatusPage\Queries\LinkedFolders;
use App\Domain\StatusPage\Queries\StatusPage;
use Illuminate\Support\Collection;

/**
 * Which projects an alert is about (monitoreo portal.AlertProjects, D-054, D-059, D-061): those
 * living on a server whose machine, agent or a service is down, serving a site that is down or
 * whose certificate is expiring, or running the PM2 app or Supervisor program. CPU, memory and
 * disk belong to none: they only go to the digest.
 */
final class AlertProjects
{
    /**
     * @param  array<string, mixed>  $alert  monitoreo's store.Alert JSON
     * @param  Collection<int, Project>  $projects
     * @return list<int> the ids of the projects it is about
     */
    public static function matching(array $alert, Collection $projects): array
    {
        $process = match ($alert['rule']) {
            AlertRules::SERVER_DOWN, AlertRules::AGENT_DOWN, AlertRules::SERVICE, AlertRules::SITE_DOWN, AlertRules::SSL_EXPIRING => null,
            AlertRules::PM2_STATUS, AlertRules::PM2_RESTARTS => self::pm2Owner($alert),
            AlertRules::SUPERVISOR => self::supervisorOwner($alert),
            default => false,
        };
        if ($process === false || $projects->isEmpty()) {
            return [];
        }
        $ids = $projects->pluck('id')->all();
        $folders = ProjectFolder::query()->withoutGlobalScopes()->whereIn('project_id', $ids)->get()->groupBy('project_id');
        $components = Component::query()->withoutGlobalScopes()->whereIn('project_id', $ids)->get()->groupBy('project_id');

        return array_values(array_filter($ids, fn (int $id) => self::belongs($alert, $folders->get($id) ?? collect(), $components->get($id) ?? collect(), $process)));
    }

    /**
     * @param  Collection<int, ProjectFolder>  $folders
     * @param  Collection<int, Component>  $components
     * @param  (callable(list<string>, array<string, true>): bool)|null  $process
     */
    private static function belongs(array $alert, Collection $folders, Collection $components, ?callable $process): bool
    {
        $serverId = (int) $alert['server_id'];
        switch ($alert['rule']) {
            case AlertRules::SERVER_DOWN:
            case AlertRules::AGENT_DOWN:
            case AlertRules::SERVICE: // every project on the server
                return $folders->contains('server_id', $serverId) || $components->contains('server_id', $serverId);
            case AlertRules::SITE_DOWN:
            case AlertRules::SSL_EXPIRING:
                return $components->contains(fn (Component $component) => $component->url !== null
                    && StatusPage::bareHost((string) parse_url($component->url, PHP_URL_HOST)) === StatusPage::bareHost((string) $alert['target']));
        }
        $paths = $folders->where('server_id', $serverId)->pluck('path')->all();
        $targets = [];
        foreach ($components as $component) {
            if ($component->server_id === $serverId && $component->target !== null) {
                $targets[$component->targetKey()] = true;
            }
        }

        return $process($paths, $targets);
    }

    /**
     * The alerted PM2 app (target "name#id@user") in the server's last PM2 snapshot, to learn where it
     * runs from. A deleted app only has its name and user.
     *
     * @return callable(list<string>, array<string, true>): bool
     */
    private static function pm2Owner(array $alert): callable
    {
        $target = (string) $alert['target'];
        $name = explode('#', $target, 2)[0];
        $at = strrpos($target, '@');
        $user = $at === false ? $target : substr($target, $at + 1);
        $cwd = '';
        foreach (self::snapshot((int) $alert['server_id'], 'pm2')['daemons'] ?? [] as $daemon) {
            foreach ($daemon['processes'] ?? [] as $process) {
                if (sprintf('%s#%d@%s', $process['name'] ?? '', $process['id'] ?? 0, $daemon['user'] ?? '') === $target) {
                    $cwd = (string) ($process['cwd'] ?? '');
                }
            }
        }

        return fn (array $folders, array $targets) => self::ownsProcess($folders, $targets, $name, "{$name}@{$user}", $cwd);
    }

    /** The same for a Supervisor program (target = its name). @return callable(list<string>, array<string, true>): bool */
    private static function supervisorOwner(array $alert): callable
    {
        $program = ['name' => (string) $alert['target']];
        foreach (self::snapshot((int) $alert['server_id'], 'supervisor')['programs'] ?? [] as $candidate) {
            if (($candidate['name'] ?? null) === $alert['target']) {
                $program = $candidate;
            }
        }

        return fn (array $folders, array $targets) => self::ownsProcess($folders, $targets, (string) $program['name'],
            (string) ($program['group'] ?? ''), (string) ($program['project'] ?? ''), (string) ($program['directory'] ?? ''));
    }

    /**
     * A process belongs to a project on one server when one of its components names it, or it runs
     * from one of its folders there. $alias is the other name a component may use: a Supervisor
     * group, or "name@user" for a PM2 app.
     *
     * @param  list<string>  $folders
     * @param  array<string, true>  $targets
     */
    private static function ownsProcess(array $folders, array $targets, string $name, string $alias, string ...$dirs): bool
    {
        if (isset($targets[$name]) || ($alias !== '' && isset($targets[$alias]))) {
            return true;
        }
        foreach ($dirs as $dir) {
            foreach ($folders as $folder) {
                if ($dir !== '' && LinkedFolders::inside($dir, $folder)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private static function snapshot(int $serverId, string $section): array
    {
        $data = ServerSnapshot::query()->withoutGlobalScopes()->where('server_id', $serverId)->where('section', $section)->value('data');
        $decoded = json_decode((string) $data, true);

        return is_array($decoded) ? $decoded : [];
    }
}
