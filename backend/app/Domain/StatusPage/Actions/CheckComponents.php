<?php

namespace App\Domain\StatusPage\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Monitoring\Models\Server;
use App\Domain\Monitoring\Models\ServerSnapshot;
use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Models\Component;
use App\Domain\StatusPage\Models\Period;
use App\Domain\StatusPage\Notifications\StatusPageMail;
use App\Domain\StatusPage\Status\ComponentState;
use App\Domain\StatusPage\Status\Evaluate;
use App\Domain\StatusPage\Status\HttpProber;
use App\Domain\StatusPage\Status\Parts;
use App\Domain\StatusPage\Status\Result;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * The status monitor (RF-13.06, monitoreo portal.Service.Tick): every minute each component of an
 * active project of an active client is probed (http/health) or judged from its server's latest
 * snapshot (pm2/supervisor/timer), and the result goes through ComponentState::next(). When a
 * component comes back from down, the client hears it works again (D-063).
 */
final class CheckComponents
{
    /** How old an agent snapshot may be before its components turn unknown: a few polls of the section. */
    private const FRESHNESS = ['pm2' => 300, 'supervisor' => 300, 'cron' => 1800];

    public function __construct(private readonly HttpProber $prober) {}

    /** One round over every monitored component, of every company. */
    public function all(): void
    {
        $components = Component::query()->withoutGlobalScope('company')
            ->whereHas('project', fn ($projects) => $projects->withoutGlobalScope('company')->where('portal_active', true)
                ->whereHas('client', fn ($clients) => $clients->withoutGlobalScope('company')->where('active', true)))
            ->orderBy('id')->get();
        $this->check($components);
    }

    /** "Chequear ahora" in the admin: the component as stored after its check. */
    public function one(Component $component): Component
    {
        $this->check(collect([$component]));

        return $component->fresh('server');
    }

    /** @param  Collection<int, Component>  $components */
    private function check(Collection $components): void
    {
        $urls = $components->filter(fn (Component $component) => in_array($component->kind, [Component::HTTP, Component::HEALTH], true) && $component->url !== null)
            ->mapWithKeys(fn (Component $component) => [$component->id => $component->url])->all();
        $probes = $this->prober->probe($urls);

        $agentKinds = $components->filter(fn (Component $component) => isset(Component::SECTION_OF[$component->kind]));
        $serverIds = $agentKinds->pluck('server_id')->filter()->unique()->values()->all();
        $servers = Server::query()->withoutGlobalScope('company')->whereIn('id', $serverIds)->get()->keyBy('id');
        $snapshots = ServerSnapshot::query()->withoutGlobalScope('company')->whereIn('server_id', $serverIds)
            ->whereIn('section', array_unique($agentKinds->map(fn (Component $component) => Component::SECTION_OF[$component->kind])->all()))
            ->get()->groupBy('section')->map(fn (Collection $bySection) => $bySection->keyBy('server_id'));

        foreach ($components as $component) {
            $now = CarbonImmutable::now()->utc();
            if (in_array($component->kind, [Component::HTTP, Component::HEALTH], true)) {
                if ($component->url === null) {
                    $result = Result::unknown('sin URL');
                } else {
                    $probe = $probes[$component->id];
                    $result = Evaluate::http($component->kind, $probe, $component->slow_ms > 0 ? $component->slow_ms : Evaluate::DEFAULT_SLOW_MS);
                    DB::table('status_checks')->insert([
                        'company_id' => $component->company_id, 'component_id' => $component->id, 'checked_at' => $now,
                        'ok' => $result->status === ComponentState::OPERATIONAL, 'status_code' => $probe->statusCode, 'latency_ms' => $probe->latencyMs,
                    ]);
                }
            } else {
                $result = $this->evaluateAgent($component, $servers, $snapshots, $now);
            }
            $this->apply($component, $result, $now);
        }
    }

    /**
     * A missing or stale snapshot, or a disabled server, is "unknown", never "down": an agent that
     * does not answer says nothing about the client's site (D-039).
     *
     * @param  Collection<int, Server>  $servers
     * @param  Collection<string, Collection<int, ServerSnapshot>>  $snapshots
     */
    private function evaluateAgent(Component $component, Collection $servers, Collection $snapshots, CarbonImmutable $now): Result
    {
        $section = Component::SECTION_OF[$component->kind];
        if ($component->server_id === null || $component->target === null) {
            return Result::unknown('sin server o sin objetivo configurado');
        }
        $server = $servers->get($component->server_id);
        if ($server === null || ! $server->enabled) {
            return Result::unknown('el server no existe o está deshabilitado');
        }
        $snapshot = $snapshots->get($section)?->get($server->id);
        if ($snapshot === null || $snapshot->collected_at === null || in_array($snapshot->data, [null, ''], true)
            || $now->getTimestamp() - $snapshot->collected_at->getTimestamp() > self::FRESHNESS[$section]) {
            return Result::unknown("sin dato reciente del agente ({$section})");
        }
        $data = json_decode($snapshot->data, true);
        if (! is_array($data)) {
            return Result::unknown("snapshot de {$section} ilegible");
        }
        $target = $component->target;

        return match ($component->kind) {
            Component::PM2 => Evaluate::pm2($target, $this->pm2Owner($component, $data), $data),
            Component::SUPERVISOR => Evaluate::supervisor($target, $data),
            default => Evaluate::timer($target, $data, $component->max_age_hours > 0 ? $component->max_age_hours : Evaluate::DEFAULT_MAX_AGE_HOURS, $now),
        };
    }

    /** A component saved without an owner adopts the only user running its app (D-089); an edit in between wins. */
    private function pm2Owner(Component $component, array $pm2): string
    {
        $owner = (string) $component->target_owner;
        $users = Evaluate::pm2Users($component->target, $pm2);
        if ($owner === '' && count($users) === 1 && $users[0] !== '') {
            $owner = $users[0];
            Component::query()->withoutGlobalScope('company')->whereKey($component->id)
                ->where('target', $component->target)->whereNull('target_owner')->toBase()->update(['target_owner' => $owner]);
        }

        return $owner;
    }

    /** Reads the stored state, applies the result and saves it with its period, one check at a time per component. */
    private function apply(Component $component, Result $result, CarbonImmutable $now): void
    {
        $back = DB::transaction(function () use ($component, $result, $now) {
            $current = Component::query()->withoutGlobalScope('company')->whereKey($component->id)->lockForUpdate()->first();
            if ($current === null) {
                return null; // deleted meanwhile
            }
            $previous = $current->status;
            [$state, $change] = $current->state()->next($result, $now);
            $current->forceFill(Component::stateColumns($state))->saveQuietly();
            if ($change !== null) {
                Period::query()->withoutGlobalScope('company')->where('component_id', $current->id)->whereNull('ended_at')
                    ->update(['ended_at' => $change['at']]);
                if ($change['open'] !== '') {
                    Period::query()->create([
                        'company_id' => $current->company_id, 'component_id' => $current->id, 'status' => $change['open'],
                        'started_at' => $change['started_at'], 'detail' => mb_substr($change['detail'], 0, 255),
                    ]);
                }
            }

            return $previous === ComponentState::DOWN && in_array($state->status, [ComponentState::OPERATIONAL, ComponentState::DEGRADED], true) ? $current : null;
        });

        if ($back !== null) {
            $this->noticeBack($back);
        }
    }

    /**
     * Mails each active user of the client that what they use works again (D-063, CA-13.16), in their
     * words. The outage itself is never mailed to the client and this mail says neither when nor how
     * long: the portal has that. One mail per user, so they never see each other's addresses.
     * Nothing leaves while newDesk runs next to monitoreo (CA-13.20).
     *
     * ponytail: one mail per component; a project-level notice if several coming back together gets noisy.
     */
    private function noticeBack(Component $component): void
    {
        if (! $component->hasParts() || ! config('newdesk.monitoring.notify')) {
            return;
        }
        $project = Project::query()->withoutGlobalScope('company')->with(['client' => fn ($query) => $query->withoutGlobalScope('company')])->find($component->project_id);
        if ($project === null || ! $project->portal_active || ! $project->client->active) {
            return;
        }
        $verb = count($component->parts) > 1 ? 'ya funcionan con normalidad' : 'ya funciona con normalidad';
        $sentence = sprintf('%s de %s %s.', Parts::label($component->parts), $project->name, $verb);
        $link = rtrim((string) config('newdesk.frontend_url'), '/')."/portal/{$project->slug}";
        $html = sprintf('<div style="font-family:system-ui,sans-serif;font-size:15px;color:#111"><p>%s</p><p>Si querés ver el detalle, <a href="%s">entrá al portal</a>.</p></div>',
            e($sentence), e($link));

        foreach ($project->client->users()->where('status', UserStatus::Active)->pluck('email') as $email) {
            Mail::to($email)->queue(new StatusPageMail("{$project->name}: {$sentence}", $html));
        }
    }
}
