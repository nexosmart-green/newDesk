<?php

namespace App\Domain\Monitoring\Discord;

use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Alerts\AlertRules;
use App\Domain\Monitoring\Contracts\DiscordGateway;
use App\Domain\Monitoring\Events\AlertChanged;
use App\Domain\Monitoring\Events\VulnerabilitiesScanned;
use App\Domain\Monitoring\Jobs\SendDiscordMessage;
use App\Domain\Monitoring\Models\Alert;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Support\ProjectSlug;
use App\Domain\Shared\Text;
use App\Domain\StatusPage\Models\ProjectFolder;
use App\Domain\StatusPage\Queries\Vulnerabilities;
use App\Domain\Support\Events\ReportSubmitted;
use App\Domain\Support\Models\Report;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

/**
 * What the panel sees about a project, posted to the project's Discord channel by the bot
 * (RF-13.09, monitoreo portal.Notifier, D-054, D-059, D-061): its alerts, active vulnerabilities,
 * the client's reports and new API errors, mentioning the project's lead. What is about a whole
 * server goes to #resumen. It also creates a new project's channel and sends the "Probar" message.
 *
 * Its alert(), vulnerabilities() and report() are queued listeners (queue `monitoring`): finding
 * the projects of an alert reads every project, the poller must not wait for that. The messages
 * go out one at a time through SendDiscordMessage. Nothing is queued nor posted while
 * `newdesk.monitoring.notify` is off or there is no bot (CA-13.20).
 */
final class Notifier implements ShouldQueue
{
    public string $queue = 'monitoring';

    /** A server, its agent or a service down are about the whole server: #resumen (D-061). */
    private const SERVER_WIDE = [AlertRules::SERVER_DOWN, AlertRules::AGENT_DOWN, AlertRules::SERVICE];

    /** Alerts whose opening was posted: only those post "Resuelto". */
    private const ANNOUNCED = 'monitoring:discord:announced:';

    private const SEVERITY_RANK = ['critical' => 0, 'high' => 1, 'moderate' => 2, 'low' => 3];

    public function __construct(private readonly DiscordGateway $discord) {}

    public function enabled(): bool
    {
        return config('newdesk.monitoring.notify') && $this->discord->configured();
    }

    /** Laravel asks before queueing a listener: with notices off nothing is even queued. */
    public function shouldQueue(object $event): bool
    {
        return $this->enabled();
    }

    /** The only way an automatic notice goes out. */
    public function post(DiscordMessage $message): void
    {
        if ($this->enabled()) {
            SendDiscordMessage::dispatch($message);
        }
    }

    /**
     * Every project with a channel, with who is pinged there.
     *
     * @return list<array{project: Project, channel: string, lead: User|null}>
     */
    public function channels(?int $companyId = null): array
    {
        if (! $this->discord->configured()) {
            return [];
        }

        return Project::query()->when($companyId !== null, fn ($query) => $query->forCompany($companyId))
            ->whereNotNull('discord_channel_id')->where('discord_channel_id', '!=', '')->with('leader')->orderBy('id')->get()
            ->map(fn (Project $project) => $this->channelOf($project))->all();
    }

    /** @return array{project: Project, channel: string, lead: User|null}|null */
    public function channelOf(Project $project): ?array
    {
        if (blank($project->discord_channel_id) || ! $this->discord->configured()) {
            return null;
        }

        return ['project' => $project, 'channel' => $project->discord_channel_id, 'lead' => $project->leader];
    }

    /**
     * "Probar": a hello to the project's channel, right away. False when the project has no channel.
     *
     * @throws DiscordError
     */
    public function test(Project $project): bool
    {
        $channel = $this->channelOf($project);
        if ($channel === null) {
            return false;
        }
        $this->discord->send($this->message($channel, '✅', 'Monitoreo quedó conectado a este canal.', [], true, self::projectUrl($project)));

        return true;
    }

    /**
     * A new project's channel (D-059, D-061): "monitoreo-<name>" in the bot's category, saved on the
     * project, with a first message that mentions the lead. The channel inherits the category's
     * permissions: only the team and the bot see it.
     *
     * @throws DiscordError
     */
    public function createChannel(Project $project): string
    {
        $project->loadMissing('client', 'leader');
        $created = $this->discord->createChannel('monitoreo-'.ProjectSlug::slugify($project->name),
            "Avisos de Monitoreo · {$project->client->displayName()} · {$project->name}");
        $project->forceFill(['discord_channel_id' => $created['id']])->save();

        $body = ['Acá van a llegar los avisos del proyecto: caídas del sitio y de sus procesos, vulnerabilidades activas, reportes del cliente y errores de la API.'];
        if (($summary = self::summaryChannel()) !== null) {
            $body[] = 'Las caídas del server y el resumen de las 9 y las 18 h van a '.DiscordMessage::channelLink($summary).'.';
        }
        $this->discord->send($this->message($this->channelOf($project), '👋', 'canal de '.DiscordMessage::escape($project->client->displayName()), $body, true,
            self::projectUrl($project)));

        return $created['id'];
    }

    /** The twice-daily digest to #resumen. */
    public function summary(string $content): void
    {
        if (($summary = self::summaryChannel()) !== null) {
            $this->post(new DiscordMessage($summary, $content));
        }
    }

    /**
     * "<icon> **title** · headline", the body lines and a last line with the leads' mentions (when
     * ping) and the link. A lead without Discord is named, not pinged; with no lead nobody is.
     *
     * @param  list<string>  $body
     * @param  list<User>  $leads
     */
    public static function compose(string $channelId, string $icon, string $title, string $headline, array $body, array $leads, bool $ping,
        string $link, bool $priority = false): DiscordMessage
    {
        $text = "{$icon} **".DiscordMessage::escape($title)."** · {$headline}";
        foreach ($body as $line) {
            $text .= "\n{$line}";
        }
        $footer = [];
        $mention = [];
        foreach ($ping ? $leads : [] as $lead) {
            if (blank($lead->discord_user_id)) {
                $footer[] = 'dev principal: '.DiscordMessage::escape($lead->email).' (sin Discord cargado)';

                continue;
            }
            $footer[] = DiscordMessage::mention($lead->discord_user_id);
            $mention[] = $lead->discord_user_id;
        }
        if ($link !== '') {
            $footer[] = "<{$link}>"; // <…> keeps Discord from embedding a preview
        }
        if ($footer !== []) {
            $text .= "\n".implode(' · ', $footer);
        }

        return new DiscordMessage($channelId, $text, $mention, $priority);
    }

    // ---------- alerts ----------

    /** monitoreo Engine.OnChange. A site down on a server that is down is covered by the server_down notice. */
    public function alert(AlertChanged $event): void
    {
        $alert = $event->alert;
        if ($event->summarized) {
            return; // its summary speaks for it (D-107)
        }
        if ($event->change === AlertChanged::SUMMARY) {
            $this->summaryAlert($alert);

            return;
        }
        if ($event->change === AlertChanged::RESOLVED && ! Cache::pull(self::ANNOUNCED.$alert['id'])) {
            return;
        }
        if ($event->change === AlertChanged::OPENED && $alert['rule'] === AlertRules::SITE_DOWN && Alert::query()->withoutGlobalScopes()
            ->where('server_id', $alert['server_id'])->where('rule', AlertRules::SERVER_DOWN)->where('state', Alert::FIRING)->exists()) {
            return;
        }
        $posted = in_array($alert['rule'], self::SERVER_WIDE, true) ? $this->serverAlert($event) : $this->projectAlert($event);
        if ($posted && $event->change !== AlertChanged::RESOLVED) {
            Cache::forever(self::ANNOUNCED.$alert['id'], true);
        }
    }

    /** Posts the alert to the channel of each project it is about. */
    private function projectAlert(AlertChanged $event): bool
    {
        $alert = $event->alert;
        $channels = $this->channels($event->companyId);
        $matched = AlertProjects::matching($alert, collect(array_column($channels, 'project')));
        if ($matched === []) {
            return false;
        }
        // Alert messages carry names that host users pick (PM2 apps): plain text only (D-078).
        [$icon, $headline, $ping] = self::headline(DiscordMessage::escape(Text::oneLine("{$alert['message']} (server {$alert['server_name']})", 300)),
            $alert, $event->change);
        foreach ($channels as $channel) {
            if (in_array($channel['project']->id, $matched, true)) {
                $this->post($this->message($channel, $icon, $headline, [], $ping, self::projectUrl($channel['project'])));
            }
        }

        return true;
    }

    /** A server's own alert to #resumen, naming the projects that live on it and pinging their leads. */
    private function serverAlert(AlertChanged $event): bool
    {
        $alert = $event->alert;
        $summary = self::summaryChannel();
        if ($summary === null) {
            return false;
        }
        $projects = Project::query()->forCompany($event->companyId)->with('leader')->orderBy('name')->orderBy('id')->get();
        $affected = $projects->whereIn('id', AlertProjects::matching($alert, $projects));
        $leads = $affected->pluck('leader')->filter()->unique('id')->values()->all();
        $names = $affected->map(fn (Project $project) => DiscordMessage::escape($project->name))->implode(', ');
        [$icon, $headline, $ping] = self::headline(DiscordMessage::escape(Text::oneLine($alert['message'], 300)), $alert, $event->change);
        $this->post(self::compose($summary, $icon, $alert['server_name'], $headline, $names === '' ? [] : ["Proyectos: {$names}"], $leads, $ping,
            self::monitoringUrl("/servers/{$alert['server_id']}"), priority: true));

        return true;
    }

    /**
     * A burst of one rule on one server as one line in #resumen (D-107). Those alerts are never
     * marked announced, so their resolutions stay silent too.
     *
     * @param  array<string, mixed>  $alert
     */
    private function summaryAlert(array $alert): void
    {
        if (($summary = self::summaryChannel()) === null) {
            return;
        }
        [$icon, $headline, $ping] = self::headline(DiscordMessage::escape(Text::oneLine($alert['message'], 300)), $alert, AlertChanged::SUMMARY);
        $this->post(self::compose($summary, $icon, $alert['server_name'], $headline, [], [], $ping, self::monitoringUrl("/servers/{$alert['server_id']}")));
    }

    /**
     * Its icon, its text and whether it pings: opened critical or escalated; "Resuelto" never pings.
     *
     * @param  array<string, mixed>  $alert
     * @return array{0: string, 1: string, 2: bool}
     */
    private static function headline(string $what, array $alert, string $change): array
    {
        if ($change === AlertChanged::RESOLVED) {
            $headline = "Resuelto: {$what}";
            if ($alert['resolved_at'] !== null) {
                $headline .= ' · duró '.self::lasted(CarbonImmutable::parse($alert['resolved_at'])->getTimestamp() - CarbonImmutable::parse($alert['first_seen_at'])->getTimestamp());
            }

            return ['✅', $headline, false];
        }

        return match (true) {
            $change === AlertChanged::ESCALATED => ['🔴', "{$what} · ahora es crítica", true],
            $alert['severity'] === AlertRules::CRITICAL => ['🔴', $what, true],
            default => ['🟠', $what, false],
        };
    }

    private static function lasted(int $seconds): string
    {
        return match (true) {
            $seconds < 60 => 'menos de un minuto',
            $seconds < 3600 => intdiv($seconds, 60).' min',
            $seconds < 48 * 3600 => intdiv($seconds, 3600).' h'.(intdiv($seconds, 60) % 60 > 0 ? ' '.(intdiv($seconds, 60) % 60).' min' : ''),
            default => intdiv($seconds, 86400).' días',
        };
    }

    // ---------- vulnerabilities ----------

    /**
     * With each new scan, each project with a channel that still has vulnerabilities hears them all,
     * pinging its lead, with the new ones marked and what got fixed (D-073). A folder only compares
     * against its own previous audit: one seen for the first time, or whose audit failed now or
     * then, has nothing "new" nor "fixed". The same scan fetched again says nothing.
     */
    public function vulnerabilities(VulnerabilitiesScanned $event): void
    {
        $before = $event->previous['scanned_at'] ?? null;
        $after = $event->next['scanned_at'] ?? null;
        if (! is_string($before) || ! is_string($after) || CarbonImmutable::parse($before)->eq(CarbonImmutable::parse($after))) {
            return;
        }
        $channels = $this->channels($event->companyId);
        if ($channels === []) {
            return;
        }
        $folders = ProjectFolder::query()->forCompany($event->companyId)->get(['project_id', 'server_id', 'path'])
            ->map(fn (ProjectFolder $folder) => $folder->only(['project_id', 'server_id', 'path']))->all();
        $names = [$event->serverId => $event->serverName];
        $previous = Vulnerabilities::assign([$event->serverId => $event->previous], $folders, $names)->byProject;
        $next = Vulnerabilities::assign([$event->serverId => $event->next], $folders, $names)->byProject;

        foreach ($channels as $channel) {
            $id = $channel['project']->id;
            $active = self::activeFindings($next[$id] ?? []);
            [$added, $fixed] = self::vulnerabilityDiff($previous[$id] ?? [], $next[$id] ?? []);
            if ($active !== [] || $fixed > 0) {
                $this->post($this->vulnerabilityMessage($channel, $active, $added, $fixed));
            }
        }
    }

    /**
     * The findings of the folders whose audit worked, the most severe first.
     *
     * @param  list<array<string, mixed>>  $dirs
     * @return list<array<string, mixed>>
     */
    private static function activeFindings(array $dirs): array
    {
        $out = [];
        foreach ($dirs as $dir) {
            if (($dir['error'] ?? '') !== '') {
                continue;
            }
            foreach ($dir['findings'] ?? [] as $finding) {
                $out[] = ['dir' => (string) $dir['path'], 'manager' => (string) ($dir['manager'] ?? ''), ...$finding];
            }
        }

        return self::sortFindings($out);
    }

    /**
     * @param  list<array<string, mixed>>  $before
     * @param  list<array<string, mixed>>  $after
     * @return array{0: list<array<string, mixed>>, 1: int} the new findings and how many were fixed
     */
    private static function vulnerabilityDiff(array $before, array $after): array
    {
        $previous = [];
        foreach ($before as $dir) {
            $previous[$dir['path'].'|'.($dir['manager'] ?? '')] = $dir;
        }
        $added = [];
        $fixed = 0;
        foreach ($after as $dir) {
            $old = $previous[$dir['path'].'|'.($dir['manager'] ?? '')] ?? null;
            if ($old === null || ($dir['error'] ?? '') !== '' || ($old['error'] ?? '') !== '') {
                continue;
            }
            $had = array_flip(array_map(self::findingKey(...), $old['findings'] ?? []));
            $has = array_flip(array_map(self::findingKey(...), $dir['findings'] ?? []));
            foreach ($dir['findings'] ?? [] as $finding) {
                if (! isset($had[self::findingKey($finding)])) {
                    $added[] = ['dir' => (string) $dir['path'], 'manager' => (string) ($dir['manager'] ?? ''), ...$finding];
                }
            }
            $fixed += count(array_diff_key($had, $has));
        }

        return [self::sortFindings($added), $fixed];
    }

    /** @param  array<string, mixed>  $finding */
    private static function findingKey(array $finding): string
    {
        return ($finding['package'] ?? '').'|'.(($finding['cve'] ?? '') ?: ($finding['title'] ?? ''));
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @return list<array<string, mixed>>
     */
    private static function sortFindings(array $findings): array
    {
        usort($findings, fn (array $a, array $b) => (self::SEVERITY_RANK[$a['severity'] ?? ''] ?? 0) <=> (self::SEVERITY_RANK[$b['severity'] ?? ''] ?? 0)
            ?: strcmp((string) ($a['package'] ?? ''), (string) ($b['package'] ?? '')));

        return $findings;
    }

    /**
     * @param  array{project: Project, channel: string, lead: User|null}  $channel
     * @param  list<array<string, mixed>>  $active
     * @param  list<array<string, mixed>>  $added
     */
    private function vulnerabilityMessage(array $channel, array $active, array $added, int $fixed): DiscordMessage
    {
        $link = self::projectUrl($channel['project']);
        $fixedLine = $fixed === 1 ? 'Se resolvió 1.' : "Se resolvieron {$fixed}.";
        if ($active === []) {
            $headline = $fixed === 1 ? 'Se resolvió 1 vulnerabilidad de dependencias' : "Se resolvieron {$fixed} vulnerabilidades de dependencias";

            return $this->message($channel, '✅', $headline, ['Ya no quedan activas.'], false, $link);
        }
        $counts = array_count_values(array_column($active, 'severity'));
        $parts = [];
        foreach (array_keys(self::SEVERITY_RANK) as $severity) {
            if (($counts[$severity] ?? 0) > 0) {
                $parts[] = "{$counts[$severity]} {$severity}";
            }
        }
        $many = count($active) !== 1;
        $headline = sprintf('%d vulnerabilidad%s activa%s para fixear (%s)', count($active), $many ? 'es' : '', $many ? 's' : '', implode(' · ', $parts));
        $new = array_flip(array_map(fn (array $finding) => $finding['dir'].'|'.$finding['manager'].'|'.self::findingKey($finding), $added));
        $body = [];
        foreach (array_slice($active, 0, 5) as $finding) {
            $line = sprintf('• %s (%s): %s — %s', DiscordMessage::escape(Text::oneLine($finding['dir'], 200)), $finding['manager'],
                DiscordMessage::escape((string) ($finding['package'] ?? '')), $finding['severity'] ?? '');
            if (($finding['cve'] ?? '') !== '') {
                $line .= ' · '.DiscordMessage::escape((string) $finding['cve']);
            }
            if (isset($new[$finding['dir'].'|'.$finding['manager'].'|'.self::findingKey($finding)])) {
                $line .= ' · nueva';
            }
            $body[] = $line;
        }
        if (count($active) > 5) {
            $body[] = sprintf('…y %d más', count($active) - 5);
        }
        if ($added !== []) {
            $body[] = sprintf('Nuevas: %d.', count($added));
        }
        if ($fixed > 0) {
            $body[] = $fixedLine;
        }

        return $this->message($channel, '🛡️', $headline, $body, true, $link);
    }

    // ---------- reports ----------

    /** A client's new report to its project's channel, pinging the lead. */
    public function report(ReportSubmitted $event): void
    {
        $report = $event->report->loadMissing('project.leader');
        $channel = $this->channelOf($report->project);
        if ($channel === null) {
            return;
        }
        $headline = 'Reporte nuevo de '.DiscordMessage::escape($report->user_email).' · '.Report::URGENCY_LABELS[$report->urgency];
        [$icon, $headline] = $report->urgency === 'blocking' ? ['🚨', "**URGENTE** · {$headline}"] : ['📣', $headline];
        if ($report->partLabel() !== null) {
            $headline .= ' · '.$report->partLabel();
        }
        $body = ['> '.DiscordMessage::escape(Text::oneLine($report->description, 300)), 'Chequeo en vivo: '.$event->live['message']];
        $this->post($this->message($channel, $icon, $headline, $body, true, self::monitoringUrl("/reportes/{$report->id}")));
    }

    // ---------- API errors ----------

    /**
     * The new errors of a project's /api/errors, grouped by exception and message, most frequent first.
     *
     * @param  array{project: Project, channel: string, lead: User|null}  $channel
     * @param  list<array{message: string, exception_class: string|null}>  $fresh
     */
    public function errorsMessage(array $channel, array $fresh, bool $ping): DiscordMessage
    {
        $groups = [];
        foreach ($fresh as $item) {
            $label = Text::oneLine($item['message'], 120);
            if (($class = (string) $item['exception_class']) !== '') {
                // The class is free text too: one line, and it must not run past the label (H17).
                $label = Text::oneLine(substr((string) strrchr('\\'.$class, '\\'), 1), 80).': '.$label;
            }
            $groups[$label] = ($groups[$label] ?? 0) + 1;
        }
        arsort($groups); // stable: equal counts keep their first-seen order

        $headline = count($fresh) === 1 ? '1 error nuevo en la API' : count($fresh).' errores nuevos en la API';
        if (count($groups) > 1) {
            $headline .= ' ('.count($groups).' distintos)';
        }
        $body = array_map(fn (string $label) => '• '.DiscordMessage::escape($label).($groups[$label] > 1 ? " ×{$groups[$label]}" : ''),
            array_slice(array_keys($groups), 0, 3));
        if (count($groups) > 3) {
            $body[] = sprintf('…y %d más', count($groups) - 3);
        }

        return $this->message($channel, '🐞', $headline, $body, $ping, self::projectUrl($channel['project']));
    }

    // ---------- shared ----------

    /**
     * @param  array{project: Project, channel: string, lead: User|null}  $channel
     * @param  list<string>  $body
     */
    private function message(array $channel, string $icon, string $headline, array $body, bool $ping, string $link): DiscordMessage
    {
        return self::compose($channel['channel'], $icon, $channel['project']->name, $headline, $body, array_filter([$channel['lead']]), $ping, $link);
    }

    private static function summaryChannel(): ?string
    {
        $id = config('newdesk.monitoring.discord.summary_channel_id');

        return filled($id) ? (string) $id : null;
    }

    /** The team's monitoring screens in the SPA. */
    public static function monitoringUrl(string $path): string
    {
        return rtrim((string) config('newdesk.frontend_url'), '/').'/monitoreo'.$path;
    }

    private static function projectUrl(Project $project): string
    {
        return self::monitoringUrl("/clientes/{$project->client_id}/proyectos/{$project->id}");
    }
}
