<?php

namespace App\Domain\StatusPage\Actions;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\StatusPage\Jobs\MeasureComponentPageSpeed;
use App\Domain\StatusPage\Models\Component;
use App\Domain\StatusPage\Models\PageSpeedRun;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * PageSpeed Insights (D-043): every web component once a week on mobile and desktop, at most 6
 * measurements per hourly pass (each takes 10-40 s). A failed run (quota, 429) is stored with its
 * error and waits a week. The API key goes in a header, never in the URL that errors quote (D-093).
 */
final class MeasurePageSpeed
{
    public const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    public const STRATEGIES = ['mobile', 'desktop'];

    private const EVERY = 7 * 86400;

    private const PER_PASS = 6;

    /** The hourly pass over every monitored web component, of every company. */
    public function due(): void
    {
        $components = Component::query()->withoutGlobalScope('company')->where('kind', Component::HTTP)->whereNotNull('url')
            ->whereHas('project', fn ($projects) => $projects->withoutGlobalScope('company')->where('portal_active', true)
                ->whereHas('client', fn ($clients) => $clients->withoutGlobalScope('company')->where('status', ClientStatus::Active)))
            ->orderBy('id')->get();
        $done = 0;
        foreach ($components as $component) {
            foreach (self::STRATEGIES as $strategy) {
                if ($done >= self::PER_PASS) {
                    return;
                }
                $last = PageSpeedRun::query()->withoutGlobalScope('company')->where('component_id', $component->id)
                    ->where('strategy', $strategy)->max('measured_at');
                if ($last !== null && CarbonImmutable::now()->getTimestamp() - CarbonImmutable::parse($last, 'UTC')->getTimestamp() < self::EVERY) {
                    continue;
                }
                $this->measure($component, $strategy);
                $done++;
            }
        }
    }

    /** "Medir ahora" from the admin: in the background, it takes a minute or two. */
    public function queue(Component $component): void
    {
        if ($component->kind !== Component::HTTP || $component->url === null) {
            throw new BusinessRuleViolation('PageSpeed mide solo los componentes web.', 'pagespeed_web_only');
        }
        MeasureComponentPageSpeed::dispatch($component->id);
    }

    /** Both strategies of one component. */
    public function component(Component $component): void
    {
        foreach (self::STRATEGIES as $strategy) {
            $this->measure($component, $strategy);
        }
    }

    public function measure(Component $component, string $strategy): PageSpeedRun
    {
        $run = new PageSpeedRun(['company_id' => $component->company_id, 'component_id' => $component->id, 'strategy' => $strategy,
            'measured_at' => CarbonImmutable::now()->utc()]);
        $key = (string) config('newdesk.monitoring.pagespeed_api_key');
        try {
            $run->fill($this->fetch((string) $component->url, $strategy, $key));
        } catch (ConnectionException|RuntimeException $e) {
            // Google's message may quote the key back.
            $message = mb_substr($key === '' ? $e->getMessage() : str_replace($key, '[key]', $e->getMessage()), 0, 250);
            $run->error = $message;
            Log::info('pagespeed: run failed', ['component' => $component->id, 'strategy' => $strategy, 'error' => $message]);
        }
        $run->save();

        return $run;
    }

    /** @return array<string, mixed> the run's metrics */
    private function fetch(string $url, string $strategy, string $key): array
    {
        $response = Http::timeout(90)->withHeaders($key === '' ? [] : ['X-Goog-Api-Key' => $key])
            ->get(self::ENDPOINT, ['url' => $url, 'strategy' => $strategy, 'category' => 'performance']);
        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException("respuesta ilegible (HTTP {$response->status()})");
        }
        if ($response->status() !== 200 || isset($body['error'])) {
            throw new RuntimeException("HTTP {$response->status()}".(isset($body['error']) ? ': '.($body['error']['message'] ?? '') : ''));
        }
        $score = $body['lighthouseResult']['categories']['performance']['score'] ?? null;
        $audit = fn (string $name) => $body['lighthouseResult']['audits'][$name]['numericValue'] ?? null;
        $round = fn ($value) => $value === null ? null : (int) round($value);
        $run = ['score' => $score === null ? null : (int) round($score * 100), 'tbt_ms' => $round($audit('total-blocking-time')), 'field' => false];

        // Real users' data (CrUX) when the site has enough traffic; the lab run otherwise (without INP).
        $field = $body['loadingExperience']['metrics'] ?? [];
        if (isset($field['LARGEST_CONTENTFUL_PAINT_MS'])) {
            $cls = $field['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'] ?? null;

            return [...$run, 'field' => true,
                'lcp_ms' => $round($field['LARGEST_CONTENTFUL_PAINT_MS']['percentile'] ?? null),
                'inp_ms' => $round($field['INTERACTION_TO_NEXT_PAINT']['percentile'] ?? null),
                'fcp_ms' => $round($field['FIRST_CONTENTFUL_PAINT_MS']['percentile'] ?? null),
                'cls' => $cls === null ? null : $cls / 100, // CrUX reports CLS ×100
            ];
        }

        return [...$run, 'lcp_ms' => $round($audit('largest-contentful-paint')), 'fcp_ms' => $round($audit('first-contentful-paint')),
            'cls' => $audit('cumulative-layout-shift')];
    }
}
