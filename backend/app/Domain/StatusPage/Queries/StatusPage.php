<?php

namespace App\Domain\StatusPage\Queries;

use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Alerts\AlertRules;
use App\Domain\Monitoring\Models\Alert;
use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Models\Component;
use App\Domain\StatusPage\Models\IncidentNote;
use App\Domain\StatusPage\Models\PageSpeedRun;
use App\Domain\StatusPage\Models\Period;
use App\Domain\StatusPage\Models\ProjectFolder;
use App\Domain\StatusPage\Status\ComponentState;
use App\Domain\StatusPage\Status\Parts;
use App\Domain\StatusPage\Status\Uptime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What a client sees of its projects (RF-13.07, monitoreo portal/view.go) and the team's preview of
 * it. Built field by field: nothing here names a server, an IP, a port or a technical component
 * (CA-13.13).
 */
final class StatusPage
{
    /** The portal's days, hours and incident titles are Argentina's, as in monitoreo. */
    public const TIMEZONE = 'America/Argentina/Buenos_Aires';

    public const INCIDENT_WINDOW_DAYS = 30;

    private const PAGESPEED_HISTORY_DAYS = 84;

    public function __construct(private readonly LinkedFolders $folders) {}

    /** GET portal/projects: the client's active projects, by name. @return list<array<string, mixed>> */
    public function cards(int $clientId): array
    {
        $now = CarbonImmutable::now()->utc();

        return Project::query()->where('client_id', $clientId)->where('portal_active', true)->orderBy('name')->get()
            ->map(function (Project $project) use ($now) {
                $components = self::components($project);
                [$status, $phrase] = self::overall($components);

                return [
                    'slug' => $project->slug,
                    'name' => $project->name,
                    'primary_color' => $project->primary_color,
                    'logo_url' => ProjectLogo::url($project, forClient: true),
                    'status' => $status,
                    'phrase' => $phrase,
                    'incident' => $this->incidentNotice($project, $components, $now),
                ];
            })->all();
    }

    /** The status page of one project (the client's, or the team's preview with its logo URL). @return array<string, mixed> */
    public function view(Project $project, ?string $logoUrl): array
    {
        $now = CarbonImmutable::now()->utc();
        $components = self::components($project);
        $periods = Period::query()->whereIn('component_id', $components->modelKeys())
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', $now->subDays(Uptime::DAYS + 1)))
            ->orderBy('started_at')->get();

        $view = self::build($project, $components, $periods, $logoUrl, $now);
        $view['incident'] = $this->incidentNotice($project, $components, $now);
        $view['last_update'] = LinkedFolders::latest($this->folders->forProject($project));
        $view['incidents'] = self::incidents($components, $now->subDays(self::INCIDENT_WINDOW_DAYS), 20, forTeam: false);
        $view['pagespeed'] = self::pageSpeedSummaries($components, $now);

        return $view;
    }

    /** {slug} only inside the client user's client, and only while shown: anything else does not exist (404). */
    public static function clientProject(User $user, string $slug): Project
    {
        return Project::query()->where('client_id', $user->client_id)->where('slug', $slug)->where('portal_active', true)->firstOrFail();
    }

    /** @return Collection<int, Component> */
    public static function components(Project $project): Collection
    {
        return Component::query()->where('project_id', $project->id)->orderBy('position')->orderBy('id')->get();
    }

    /**
     * The project status as the client sees it (the worst part) and its sentence: "Degradado: App —
     * responde lento" or "Caído: problemas en Web y App".
     *
     * @param  iterable<Component>  $components
     * @return array{0: string, 1: string}
     */
    public static function overall(iterable $components): array
    {
        $parts = Parts::states($components);
        if ($parts === []) {
            return [ComponentState::UNKNOWN, 'Todavía no hay chequeos configurados'];
        }
        $worst = ComponentState::worst(...array_column($parts, 'status'));
        if ($worst === ComponentState::OPERATIONAL) {
            return [$worst, 'Todo funciona normalmente'];
        }
        if ($worst === ComponentState::UNKNOWN) {
            return [$worst, 'Sin datos recientes'];
        }
        $culprits = array_values(array_filter($parts, fn (array $part) => $part['status'] === $worst));
        if (count($culprits) === 1) {
            return [$worst, sprintf('%s: %s — %s', ComponentState::LABELS[$worst], $culprits[0]['label'], mb_strtolower($culprits[0]['phrase']))];
        }

        return [$worst, sprintf('%s: problemas en %s', ComponentState::LABELS[$worst], Parts::label(array_column($culprits, 'key')))];
    }

    /**
     * monitoreo's NewStatus: everything but what needs more queries. $periods must cover the last
     * Uptime::DAYS days.
     *
     * @param  Collection<int, Component>  $components
     * @param  Collection<int, Period>  $periods
     * @return array<string, mixed>
     */
    public static function build(Project $project, Collection $components, Collection $periods, ?string $logoUrl, CarbonImmutable $now): array
    {
        [$status, $phrase] = self::overall($components);
        $byComponent = $periods->groupBy('component_id');
        [$checkedAt, $ssl, $backup] = [null, null, null];
        // What the client sees is the parts: their uptime counts only the components that affect some part, from the first of them on.
        [$start, $visible] = [$now, []];
        foreach ($components as $component) {
            if ($component->hasParts()) {
                $start = $start->min($component->created_at ?? $now);
                $visible = [...$visible, ...($byComponent[$component->id] ?? [])];
            }
            if ($component->last_checked_at !== null && ($checkedAt === null || $component->last_checked_at->gt($checkedAt))) {
                $checkedAt = $component->last_checked_at;
            }
            $expires = $component->ssl_expires_at;
            if ($expires !== null && in_array($component->kind, [Component::HTTP, Component::HEALTH], true) && ($ssl === null || $expires->lt($ssl))) {
                $ssl = $expires;
            }
            if ($component->kind === Component::TIMER && $backup === null) {
                $backup = ['last_ok_at' => $component->last_ok_at?->toIso8601ZuluString(), 'status' => $component->status];
            }
        }

        $parts = [];
        foreach (Parts::states($components) as $i => $part) {
            [$partStart, $partPeriods] = [$now, []];
            foreach ($part['components'] as $member) {
                $partStart = $partStart->min($member->created_at ?? $now);
                $partPeriods = [...$partPeriods, ...($byComponent[$member->id] ?? [])];
            }
            $parts[] = [
                'id' => $i + 1, 'label' => $part['label'], 'kind' => $part['key'], 'status' => $part['status'], 'phrase' => $part['phrase'],
                'since' => $part['since']?->toIso8601ZuluString(),
                'uptime_month_pct' => Uptime::compute($partPeriods, $partStart, $now, self::TIMEZONE)['month_pct'],
            ];
        }

        $domain = null;
        if ($project->domain !== null) {
            [$expiresAt, $left] = self::domainExpiry($project, $now);
            $domain = ['name' => $project->domain, 'expires_at' => $expiresAt?->toIso8601ZuluString(), 'days_left' => $expiresAt === null ? null : $left];
        }

        return [
            'slug' => $project->slug,
            'name' => $project->name,
            'client_name' => $project->client->name,
            'primary_color' => $project->primary_color,
            'logo_url' => $logoUrl,
            'status' => $status,
            'phrase' => $phrase,
            'incident' => false,
            'checked_at' => $checkedAt?->toIso8601ZuluString(),
            'components' => $parts,
            'uptime' => Uptime::compute($visible, $start, $now, self::TIMEZONE),
            'ssl' => $ssl === null ? null : ['expires_at' => $ssl->toIso8601ZuluString(), 'days_left' => (int) floor(($ssl->getTimestamp() - $now->getTimestamp()) / 86400)],
            'domain' => $domain,
            'backup' => $backup,
            'last_update' => null,
            'incidents' => [],
            'pagespeed' => [],
        ];
    }

    /**
     * The domain's expiry as a calendar day (D-040): the looked-up date (read in Argentina) or else
     * the manual one, at noon UTC so every time zone shows the same day, and the calendar days left.
     *
     * @return array{0: CarbonImmutable|null, 1: int}
     */
    public static function domainExpiry(Project $project, CarbonImmutable $now): array
    {
        $day = $project->domain_expires_at?->setTimezone(self::TIMEZONE)->format('Y-m-d') ?? $project->domain_expires_manual?->format('Y-m-d');
        if ($day === null) {
            return [null, 0];
        }
        $date = CarbonImmutable::parse("{$day} 12:00:00", 'UTC');
        $today = CarbonImmutable::parse($now->setTimezone(self::TIMEZONE)->format('Y-m-d').' 12:00:00', 'UTC');

        return [$date, (int) round(($date->getTimestamp() - $today->getTimestamp()) / 86400)];
    }

    /**
     * The down periods after $since (D-042), open ones first, with the team's notes. The client only
     * sees components that affect a part, named by those parts; the team every component by its
     * label, and who wrote each note.
     *
     * @param  Collection<int, Component>  $components
     * @return list<array<string, mixed>>
     */
    public static function incidents(Collection $components, CarbonImmutable $since, int $limit, bool $forTeam): array
    {
        $byId = $components->filter(fn (Component $component) => $forTeam || $component->hasParts())->keyBy('id');
        $periods = Period::query()->whereIn('component_id', $byId->keys())->where('status', ComponentState::DOWN)
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', $since))
            ->orderByRaw('ended_at IS NULL DESC')->orderByDesc('started_at')->limit($limit)->get();
        $notes = IncidentNote::query()->whereIn('period_id', $periods->modelKeys())->orderBy('created_at')->orderBy('id')->get()->groupBy('period_id');

        return $periods->map(function (Period $period) use ($byId, $notes, $forTeam) {
            $component = $byId[$period->component_id];

            return [
                'id' => $period->id,
                'component' => $forTeam ? $component->label : Parts::label($component->parts),
                'status' => $period->ended_at === null ? 'ongoing' : 'resolved',
                'started_at' => $period->started_at->toIso8601ZuluString(),
                'ended_at' => $period->ended_at?->toIso8601ZuluString(),
                'title' => self::incidentTitle($component, $period->started_at, $period->ended_at),
                'notes' => ($notes[$period->id] ?? collect())->map(fn (IncidentNote $note) => $note->toPayload($forTeam))->values()->all(),
            ];
        })->all();
    }

    /**
     * What happened, in the client's words and time zone, by the parts the component affects: "App y
     * CMS no respondieron el 7/10 entre las 14:02 y las 14:09." Without parts, its label.
     */
    public static function incidentTitle(Component $component, CarbonImmutable $started, ?CarbonImmutable $ended): string
    {
        [$subject, $plural] = $component->hasParts() ? [Parts::label($component->parts), count($component->parts) > 1] : [$component->label, false];
        [$past, $present] = $plural ? ['no respondieron', 'no responden'] : ['no respondió', 'no responde'];
        if (in_array($component->kind, [Component::PM2, Component::SUPERVISOR], true)) {
            [$past, $present] = $plural
                ? ['funcionaron con problemas', 'funcionan con problemas']
                : ['funcionó con problemas', 'funciona con problemas'];
        }
        $day = fn (CarbonImmutable $at) => $at->format('j/n');
        $start = $started->setTimezone(self::TIMEZONE);
        if ($ended === null) {
            return sprintf('%s %s desde el %s a las %s.', $subject, $present, $day($start), $start->format('H:i'));
        }
        $end = $ended->setTimezone(self::TIMEZONE);
        if ($day($start) === $day($end)) {
            return sprintf('%s %s el %s entre las %s y las %s.', $subject, $past, $day($start), $start->format('H:i'), $end->format('H:i'));
        }

        return sprintf('%s %s desde el %s a las %s hasta el %s a las %s.', $subject, $past, $day($start), $start->format('H:i'), $day($end), $end->format('H:i'));
    }

    /**
     * Tells the client we already know something is wrong (D-051): the project is down, a server it
     * lives on is down, or one of its own sites is down. An alert counts once it has been firing
     * DOWN_AFTER (D-071). agent_down is left out on purpose: a dead agent is our blind spot, not the
     * client's outage.
     *
     * @param  Collection<int, Component>  $components
     */
    public function incidentNotice(Project $project, Collection $components, CarbonImmutable $now): bool
    {
        if (self::overall($components)[0] === ComponentState::DOWN) {
            return true;
        }
        $firing = Alert::query()->where('state', Alert::FIRING)->whereIn('rule', [AlertRules::SERVER_DOWN, AlertRules::SITE_DOWN])
            ->where('first_seen_at', '<=', $now->subSeconds(ComponentState::DOWN_AFTER))->get(['server_id', 'rule', 'target']);
        if ($firing->isEmpty()) {
            return false;
        }
        $servers = ProjectFolder::query()->where('project_id', $project->id)->pluck('server_id')
            ->merge($components->pluck('server_id')->filter())->unique()->all();
        $hosts = $components->pluck('url')->filter()->map(fn (string $url) => self::bareHost((string) parse_url($url, PHP_URL_HOST)))->all();

        return $firing->contains(fn (Alert $alert) => ($alert->rule === AlertRules::SERVER_DOWN && in_array((int) $alert->server_id, array_map('intval', $servers), true))
            || ($alert->rule === AlertRules::SITE_DOWN && in_array(self::bareHost($alert->target), $hosts, true)));
    }

    /**
     * Per web component, the latest successful run of each strategy and the scores of the last 12
     * weeks (D-043). Failed runs are left out; a component never measured has nothing to show.
     *
     * @param  Collection<int, Component>  $components
     * @return list<array<string, mixed>>
     */
    public static function pageSpeedSummaries(Collection $components, CarbonImmutable $now): array
    {
        $summaries = [];
        foreach ($components as $component) {
            if ($component->kind === Component::HTTP && $component->hasParts()) {
                $summaries[$component->id] = ['component' => Parts::label($component->parts), 'mobile' => null, 'desktop' => null, 'history' => []];
            }
        }
        $runs = PageSpeedRun::query()->whereIn('component_id', array_keys($summaries))
            ->where('measured_at', '>=', $now->subDays(self::PAGESPEED_HISTORY_DAYS))->orderBy('measured_at')->orderBy('id')->get();
        foreach ($runs as $run) {
            if ($run->error !== null || $run->score === null) {
                continue;
            }
            $summaries[$run->component_id][$run->strategy === 'mobile' ? 'mobile' : 'desktop'] = $run->toPayload();
            $summaries[$run->component_id]['history'][] = ['measured_at' => $run->measured_at->toIso8601ZuluString(), 'strategy' => $run->strategy, 'score' => $run->score];
        }

        return array_values(array_filter($summaries, fn (array $summary) => $summary['mobile'] !== null || $summary['desktop'] !== null));
    }

    /** A site's host as alerts and components are matched: lower case, without "www.". */
    public static function bareHost(string $host): string
    {
        $host = strtolower(trim($host));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
