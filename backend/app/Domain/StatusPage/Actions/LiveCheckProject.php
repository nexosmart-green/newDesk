<?php

namespace App\Domain\StatusPage\Actions;

use App\Domain\Monitoring\Contracts\AgentGateway;
use App\Domain\Monitoring\Models\Server;
use App\Domain\Monitoring\Polling\Poller;
use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Models\Component;
use App\Domain\StatusPage\Queries\StatusPage;
use App\Domain\StatusPage\Status\ComponentState;
use App\Domain\StatusPage\Status\Parts;
use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Collection;

/**
 * Checks every component of a project now, for a problem report (monitoreo Service.LiveCheck,
 * D-041): http/health probed right away, pm2/supervisor/timer judged after asking their agents for
 * a fresh section. Whatever does not finish within the budget is "Sin verificar". The results also
 * feed the monitor (same debounce as every minute); the answer uses them as they are, because the
 * client asked "now".
 */
final class LiveCheckProject
{
    /** The request waits for it (monitoreo LiveCheckBudget). */
    public const BUDGET_SECONDS = 20;

    private const PUBLIC_KIND = [Component::HTTP => 'web', Component::HEALTH => 'api', Component::PM2 => 'process',
        Component::SUPERVISOR => 'process', Component::TIMER => 'backup'];

    private const ARTICLE = ['Web' => 'la Web', 'App' => 'la App', 'CMS' => 'el CMS'];

    public function __construct(
        private readonly CheckComponents $check,
        private readonly Poller $poller,
        private readonly AgentGateway $gateway,
    ) {}

    /**
     * monitoreo's Live JSON. `components` and their `detail` are for the team; the client sees `parts`.
     *
     * @return array{status: string, message: string, checked_at: string, components: list<array<string, mixed>>, parts: list<array{label: string, status: string, phrase: string}>}
     */
    public function __invoke(Project $project): array
    {
        $deadline = microtime(true) + self::BUDGET_SECONDS;
        $components = StatusPage::components($project);
        [$agentBased, $probed] = $components->partition(fn (Component $component) => isset(Component::SECTION_OF[$component->kind]));

        // ponytail: the probes block PHP's only loop, so the agent transfers started here only move on
        // after them; both share the budget. Run the probes through the gateway's loop if that ever matters.
        $refreshes = $this->refreshAgents($agentBased);
        $results = $this->check->check($probed);
        if ($this->settled($refreshes, $deadline)) {
            $results += $this->check->check($agentBased);
        }

        $live = $components->map(function (Component $component) use ($results) {
            $result = $results[$component->id] ?? null;

            return [
                'label' => $component->label,
                'kind' => self::PUBLIC_KIND[$component->kind],
                'parts' => $component->parts,
                'status' => $result->status ?? ComponentState::UNKNOWN,
                'phrase' => $result->phrase ?? 'Sin verificar',
                'detail' => $result->detail ?? 'no terminó a tiempo',
                'latency_ms' => $result?->latencyMs,
                'status_code' => $result?->statusCode,
            ];
        })->values()->all();
        $parts = self::parts($live);
        [$status, $message] = self::message($parts);

        return ['status' => $status, 'message' => $message, 'checked_at' => CarbonImmutable::now()->utc()->toJSON(), 'components' => $live, 'parts' => $parts];
    }

    /**
     * Asks each (server, section) the components read for a fresh answer, once each.
     *
     * @param  Collection<int, Component>  $components
     * @return list<PromiseInterface>
     */
    private function refreshAgents(Collection $components): array
    {
        $servers = $components->isEmpty() ? collect() : Server::query()->forCompany($components->first()->company_id)
            ->whereIn('id', $components->pluck('server_id')->filter()->unique()->all())->get()->keyBy('id');
        $refreshes = [];
        foreach ($components as $component) {
            $server = $servers->get($component->server_id);
            $section = Component::SECTION_OF[$component->kind];
            if ($server !== null && ! isset($refreshes["{$server->id}:{$section}"])) {
                $refreshes["{$server->id}:{$section}"] = $this->poller->pollNow($server, $section);
            }
        }
        $this->gateway->tick();

        return array_values($refreshes);
    }

    /** @param  list<PromiseInterface>  $promises */
    private function settled(array $promises, float $deadline): bool
    {
        while (true) {
            $pending = array_filter($promises, fn (PromiseInterface $promise) => $promise->getState() === PromiseInterface::PENDING);
            if ($pending === []) {
                return true;
            }
            if (microtime(true) >= $deadline) {
                return false;
            }
            $this->gateway->tick();
            usleep(10_000);
        }
    }

    /**
     * The client's parts (Web, App, CMS) in order: the worst of the components that affect each one,
     * with the phrase of the first in that state.
     *
     * @param  list<array<string, mixed>>  $live
     * @return list<array{label: string, status: string, phrase: string}>
     */
    private static function parts(array $live): array
    {
        $parts = [];
        foreach (Parts::ORDER as $key) {
            $members = array_values(array_filter($live, fn (array $component) => in_array($key, $component['parts'], true)));
            if ($members === []) {
                continue;
            }
            $status = ComponentState::worst(...array_column($members, 'status'));
            $first = array_values(array_filter($members, fn (array $component) => $component['status'] === $status))[0];
            $parts[] = ['label' => Parts::LABELS[$key], 'status' => $status, 'phrase' => $first['phrase']];
        }

        return $parts;
    }

    /**
     * What the client reads right after reporting, by parts.
     *
     * @param  list<array{label: string, status: string, phrase: string}>  $parts
     * @return array{0: string, 1: string}
     */
    private static function message(array $parts): array
    {
        $worst = ComponentState::worst(...array_column($parts, 'status'));
        if (! ComponentState::isBad($worst)) {
            return [$worst, 'Desde nuestro lado todo responde bien. Ya recibimos tu reporte y lo revisamos. '
                .'Si el problema sigue, puede ser la conexión o el navegador: probá desde otro dispositivo o red.'];
        }
        $bad = array_values(array_filter($parts, fn (array $part) => ComponentState::isBad($part['status'])));
        $names = array_map(fn (array $part) => self::ARTICLE[$part['label']], $bad);
        if (count($bad) === 1) {
            return [$worst, sprintf('Detectamos un problema en %s: %s. El equipo ya está avisado.', $names[0], mb_strtolower($bad[0]['phrase']))];
        }

        return [$worst, sprintf('Detectamos problemas en %s. El equipo ya está avisado.', implode(', ', array_slice($names, 0, -1)).' y '.end($names))];
    }
}
