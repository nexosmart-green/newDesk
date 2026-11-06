<?php

namespace App\Domain\StatusPage\Queries;

use App\Domain\Monitoring\Sites\SiteTargets;
use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Models\Component;
use App\Domain\StatusPage\Models\ProjectFolder;
use App\Domain\StatusPage\Status\Parts;

/**
 * Components proposed from what the agents see around the linked folders (D-044): the vhosts that
 * serve them (if they serve 2 projects or fewer), the PM2 apps and Supervisor programs that run
 * from them and the backup timers named like them. The team reviews them; adding one uses the
 * regular component input.
 */
final class Suggestions
{
    /** clientes.nexosmart.com.ar serves ~17 projects by location: that vhost is nobody's site. */
    private const SHARED_VHOST = 2;

    /** These never tie a backup timer to a folder by name. */
    private const GENERIC_WORDS = ['backend', 'frontend', 'front', 'back', 'staging', 'laravel', 'admin', 'backup', 'timer', 'service', 'nexosmart', 'clientes'];

    /** @return list<array<string, mixed>> */
    public function __invoke(Project $project): array
    {
        $folders = ProjectFolder::query()->with('server:id,name')->where('project_id', $project->id)->get()
            ->sortBy([fn ($a, $b) => strcmp($a->server->name, $b->server->name), fn ($a, $b) => strcmp($a->path, $b->path)]);
        if ($folders->isEmpty()) {
            return [];
        }
        $components = StatusPage::components($project);
        $sections = LinkedFolders::sections($folders->pluck('server_id')->unique()->values()->all(), ['sites', 'pm2', 'supervisor', 'cron']);

        $out = [];
        $seen = [];
        $add = function (array $suggestion) use (&$out, &$seen, $components) {
            $key = implode('|', [$suggestion['kind'], $suggestion['url'] ?? '', $suggestion['server_id'] ?? 0, ($suggestion['target'] ?? '').'@'.($suggestion['target_owner'] ?? '')]);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $suggestion['exists'] = $components->contains(fn (Component $component) => $component->kind === $suggestion['kind'] && (
                (isset($suggestion['url']) && $component->url === $suggestion['url'])
                || (isset($suggestion['target']) && $component->target === $suggestion['target'] && $component->server_id === $suggestion['server_id']
                    && in_array($component->target_owner ?? '', ['', $suggestion['target_owner'] ?? ''], true))
            ));
            $out[] = $suggestion;
        };

        foreach ($folders as $folder) {
            [$serverId, $serverName, $path] = [$folder->server_id, $folder->server->name, $folder->path];
            $short = basename($path);

            foreach ($sections['sites'][$serverId]['items'] ?? [] as $site) {
                $served = $site['projects'] ?? [];
                if (! array_filter($served, fn ($dir) => LinkedFolders::inside((string) $dir, $path)) || count($served) > self::SHARED_VHOST) {
                    continue;
                }
                foreach (SiteTargets::siteNames($site) as $name) {
                    $reason = "{$name} lo sirve {$serverName} · {$path}";
                    if (str_starts_with($name, 'api')) {
                        $add(['kind' => Component::HEALTH, 'label' => 'API', 'parts' => null, 'url' => "https://{$name}/health", 'reason' => $reason.' (revisá que exista /health)']);
                    } else {
                        $part = Parts::place($name, $short);
                        $add(['kind' => Component::HTTP, 'label' => Parts::LABELS[$part], 'parts' => [$part], 'url' => "https://{$name}/", 'reason' => $reason]);
                    }
                }
            }

            foreach ($sections['pm2'][$serverId]['daemons'] ?? [] as $daemon) {
                foreach ($daemon['processes'] ?? [] as $process) {
                    $cwd = (string) ($process['cwd'] ?? '');
                    if (($process['is_module'] ?? false) || ! LinkedFolders::inside($cwd, $path)) {
                        continue;
                    }
                    $add(['kind' => Component::PM2, 'label' => 'Procesos', 'parts' => null, 'server_id' => $serverId, 'server_name' => $serverName,
                        'target' => (string) ($process['name'] ?? ''), 'target_owner' => (string) ($daemon['user'] ?? ''),
                        'reason' => "app de PM2 que corre en {$serverName} desde {$cwd}"]);
                }
            }

            foreach ($sections['supervisor'][$serverId]['programs'] ?? [] as $program) {
                if (! LinkedFolders::inside((string) ($program['project'] ?? ''), $path) && ! LinkedFolders::inside((string) ($program['directory'] ?? ''), $path)) {
                    continue;
                }
                $add(['kind' => Component::SUPERVISOR, 'label' => 'Workers', 'parts' => null, 'server_id' => $serverId, 'server_name' => $serverName,
                    'target' => ($program['group'] ?? '') !== '' ? $program['group'] : (string) ($program['name'] ?? ''),
                    'reason' => "programa de Supervisor de {$serverName} que corre desde {$path}"]);
            }

            $words = self::significantWords("{$short} {$project->slug}");
            foreach ($sections['cron'][$serverId]['timers'] ?? [] as $timer) {
                if (! ($timer['custom'] ?? false)) {
                    continue;
                }
                foreach ($words as $word) {
                    if (str_contains(strtolower((string) ($timer['unit'] ?? '')), $word)) {
                        $add(['kind' => Component::TIMER, 'label' => 'Backups', 'parts' => [], 'server_id' => $serverId, 'server_name' => $serverName,
                            'target' => (string) $timer['unit'], 'reason' => "timer de {$serverName} con un nombre parecido a \"{$word}\""]);
                        break;
                    }
                }
            }
        }

        // The API and the processes serve every part the project has or gets with these suggestions; the team unticks what does not apply.
        $present = Parts::present($components);
        foreach ($out as $suggestion) {
            $present = [...$present, ...($suggestion['parts'] ?? [])];
        }
        $present = Parts::normalize(array_values(array_unique($present))) ?: [Parts::WEB];
        foreach ($out as &$suggestion) {
            $suggestion['parts'] ??= $present;
        }
        unset($suggestion);

        return self::distinctLabels($out);
    }

    /** "tol_frontend_web playinn" → the words that can name a project in a timer: 4+ letters, not generic. @return list<string> */
    private static function significantWords(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($words, fn (string $word) => strlen($word) >= 4 && ! in_array($word, self::GENERIC_WORDS, true)));
    }

    /** Repeated labels tell their components apart: "Web · playinn.com" and "Web · www.playinn.com". */
    private static function distinctLabels(array $suggestions): array
    {
        $count = array_count_values(array_column($suggestions, 'label'));
        foreach ($suggestions as &$suggestion) {
            if ($count[$suggestion['label']] < 2) {
                continue;
            }
            $detail = $suggestion['target'] ?? '';
            if (isset($suggestion['url'])) {
                $detail = preg_replace(['#^https://#', '#/$#', '#/health$#'], '', $suggestion['url']);
            }
            $suggestion['label'] .= ' · '.$detail;
        }

        return $suggestions;
    }
}
