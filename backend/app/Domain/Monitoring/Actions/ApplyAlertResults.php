<?php

namespace App\Domain\Monitoring\Actions;

use App\Domain\Monitoring\Alerts\AlertResult;
use App\Domain\Monitoring\Alerts\AlertRules;
use App\Domain\Monitoring\Alerts\AlertState;
use App\Domain\Monitoring\Events\AlertBroadcast;
use App\Domain\Monitoring\Events\AlertChanged;
use App\Domain\Monitoring\Models\Alert;
use App\Domain\Monitoring\Models\Server;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * RF-13.04, monitoreo alerts.Engine: opens an alert once its target fired the rule's evaluations
 * in a row for long enough, refreshes it while it fires (escalating a warning that turns
 * critical) and resolves it when it stops firing or its target disappears. No reminders.
 */
final class ApplyAlertResults
{
    /** Past this many openings of one rule in one pass (per daemon user), notices get one summary (D-107). */
    public const MAX_OPEN_NOTICES = 10;

    private const SUMMARY_TARGETS = 5;

    public function __construct(private readonly AlertState $state) {}

    /** @param  list<AlertResult>  $results */
    public function __invoke(Server $server, array $results, ?CarbonImmutable $now = null): void
    {
        $now = ($now ?? CarbonImmutable::now())->utc();
        foreach ($results as $result) {
            // The poller, the site-check worker and any other process may evaluate the same server.
            $events = Cache::lock(AlertState::lockKey($server->id, $result->rule), 30)
                ->block(5, fn () => $this->apply($server, $result, $now));
            foreach ($events as $event) {
                $this->dispatch($event);
            }
        }
    }

    /** @return list<AlertChanged> */
    private function apply(Server $server, AlertResult $result, CarbonImmutable $now): array
    {
        $open = Alert::query()->where('server_id', $server->id)->where('rule', $result->rule)
            ->where('state', Alert::FIRING)->get()->keyBy('target');
        $streaks = $this->state->streaks($server->id, $result->rule);
        $observed = [];
        $opened = [];
        $events = [];

        foreach ($result->observations as $observation) {
            $observed[$observation->target] = true;
            $existing = $open->get($observation->target);

            if (! $observation->firing) {
                unset($streaks[$observation->target]);
                if ($existing !== null) {
                    $events[] = $this->resolve($server, $existing, $now);
                }

                continue;
            }

            $streak = $streaks[$observation->target] ?? ['since' => $now->getTimestamp(), 'count' => 0];
            $streak['count']++;
            $streaks[$observation->target] = $streak;
            $ready = $streak['count'] >= max($result->required, 1) && $now->getTimestamp() - $streak['since'] >= $result->for;

            if ($existing !== null) {
                $escalated = $existing->severity !== AlertRules::CRITICAL && $observation->severity === AlertRules::CRITICAL;
                $existing->forceFill([
                    'severity' => $observation->severity,
                    'message' => mb_substr($observation->message, 0, 512),
                    'value' => mb_substr($observation->value, 0, 64),
                    'last_seen_at' => $now,
                ])->save();
                if ($escalated) {
                    $events[] = $this->changed($server, $existing, AlertChanged::ESCALATED);
                }
            } elseif ($ready) {
                $opened[] = Alert::query()->create([
                    'company_id' => $server->company_id,
                    'server_id' => $server->id,
                    'rule' => $result->rule,
                    'target' => mb_substr($observation->target, 0, 255),
                    'severity' => $observation->severity,
                    'state' => Alert::FIRING,
                    'message' => mb_substr($observation->message, 0, 512),
                    'value' => mb_substr($observation->value, 0, 64),
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                ]);
            }
        }

        // Targets that disappeared (an unmounted disk, a deleted PM2 process) resolve.
        foreach ($open as $target => $alert) {
            if (! isset($observed[$target])) {
                unset($streaks[$target]);
                $events[] = $this->resolve($server, $alert, $now);
            }
        }
        $this->state->saveStreaks($server->id, $result->rule, $streaks);

        return [...$events, ...$this->announce($server, $result->rule, $opened, $now)];
    }

    private function resolve(Server $server, Alert $alert, CarbonImmutable $now): AlertChanged
    {
        $alert->forceFill(['state' => Alert::RESOLVED, 'resolved_at' => $now, 'last_seen_at' => $now])->save();

        return $this->changed($server, $alert, AlertChanged::RESOLVED);
    }

    /**
     * The alerts one pass opened, grouped by the daemon user in their target ("name#id@user"): a
     * group larger than MAX_OPEN_NOTICES is summarized, so one user's flood cannot swallow another
     * user's real alert.
     *
     * @param  list<Alert>  $opened
     * @return list<AlertChanged>
     */
    private function announce(Server $server, string $rule, array $opened, CarbonImmutable $now): array
    {
        $groups = [];
        foreach ($opened as $alert) {
            $at = strrpos($alert->target, '@');
            $groups[$at === false ? '' : substr($alert->target, $at + 1)][] = $alert;
        }

        $events = [];
        foreach ($groups as $group) {
            $summarized = count($group) > self::MAX_OPEN_NOTICES;
            foreach ($group as $alert) {
                $events[] = $this->changed($server, $alert, AlertChanged::OPENED, $summarized);
            }
            if ($summarized) {
                $events[] = $this->summary($server, $rule, $group, $now);
            }
        }

        return $events;
    }

    /** @param  list<Alert>  $group */
    private function summary(Server $server, string $rule, array $group, CarbonImmutable $now): AlertChanged
    {
        $targets = array_map(fn (Alert $alert) => $alert->target, array_slice($group, 0, self::SUMMARY_TARGETS));
        $critical = array_filter($group, fn (Alert $alert) => $alert->severity === AlertRules::CRITICAL) !== [];
        $summary = (new Alert)->forceFill([
            'company_id' => $server->company_id,
            'server_id' => $server->id,
            'rule' => $rule,
            'target' => '',
            'severity' => $critical ? AlertRules::CRITICAL : AlertRules::WARNING,
            'state' => Alert::FIRING,
            'message' => sprintf('%d alertas %s nuevas a la vez: %s y %d más', count($group), $rule, implode(', ', $targets), count($group) - count($targets)),
            'value' => (string) count($group),
            'first_seen_at' => $now,
            'last_seen_at' => $now,
        ]);

        return $this->changed($server, $summary, AlertChanged::SUMMARY);
    }

    private function changed(Server $server, Alert $alert, string $change, bool $summarized = false): AlertChanged
    {
        $alert->setRelation('server', $server);

        return new AlertChanged($server->company_id, $alert->toPayload(), $change, $summarized);
    }

    /**
     * The notices (Discord) and the browsers apart: Reverb being down must not stop a notice, and
     * neither may undo what was saved.
     */
    private function dispatch(AlertChanged $event): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            Log::error('monitoring alerts: notice failed', ['change' => $event->change, 'error' => $e->getMessage()]);
            report($e);
        }
        try {
            event(new AlertBroadcast($event));
        } catch (Throwable $e) {
            Log::warning('monitoring alerts: broadcast failed', ['change' => $event->change, 'error' => $e->getMessage()]);
        }
    }
}
