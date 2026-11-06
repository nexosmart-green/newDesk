<?php

namespace App\Domain\StatusPage\Actions;

use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Notifications\StatusPageMail;
use App\Domain\StatusPage\Queries\StatusPage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Domain expiry (D-040): once a day per project with a domain, RDAP is asked for the expiration
 * (rdap.org redirects to the registry); without an answer the date the team typed counts. The team
 * gets one mail at 30 days and one at 7; a renewal starts the notices over.
 */
final class CheckDomains
{
    public const CHECK_EVERY = 86400;

    private const NOTICES = [30, 7];

    public function __invoke(): void
    {
        $now = CarbonImmutable::now()->utc();
        $projects = Project::query()->withoutGlobalScope('company')->with(['client' => fn ($query) => $query->withoutGlobalScope('company')])
            ->whereNotNull('domain')->where('portal_active', true)
            ->whereHas('client', fn ($clients) => $clients->withoutGlobalScope('company')->where('active', true))
            ->orderBy('id')->get();

        foreach ($projects as $project) {
            if ($project->domain_checked_at === null || $now->getTimestamp() - $project->domain_checked_at->getTimestamp() >= self::CHECK_EVERY) {
                $this->lookUp($project, $now);
            }
            $this->notice($project, $now);
        }
    }

    /** A renewal (a later date than the stored one) starts the notices over; a failed lookup keeps what was known. */
    private function lookUp(Project $project, CarbonImmutable $now): void
    {
        try {
            $expires = $this->rdap((string) $project->domain);
        } catch (Throwable $e) {
            Log::info('status page: domain lookup failed', ['domain' => $project->domain, 'error' => $e->getMessage()]);
            $expires = null;
        }
        $renewed = $expires !== null && $project->domain_expires_at !== null && $expires->gt($project->domain_expires_at);
        Project::withoutTimestamps(fn () => $project->forceFill([
            'domain_notice_level' => $renewed ? 0 : $project->domain_notice_level,
            'domain_expires_at' => $expires ?? $project->domain_expires_at,
            'domain_checked_at' => $now,
        ])->save());
    }

    private function notice(Project $project, CarbonImmutable $now): void
    {
        [$expires, $left] = StatusPage::domainExpiry($project, $now);
        if ($expires === null) {
            return;
        }
        $level = 0;
        foreach (self::NOTICES as $i => $threshold) {
            $level = $left <= $threshold ? $i + 1 : $level;
        }
        $recipient = config('newdesk.monitoring.alert_email');
        // While newDesk runs next to monitoreo nothing is sent, and the level waits for the cutover (CA-13.20).
        if ($level <= $project->domain_notice_level || ! config('newdesk.monitoring.notify') || blank($recipient)) {
            return;
        }
        $domain = (string) $project->domain;
        $subject = $left < 0 ? "[AVISO] El dominio {$domain} está vencido" : "[AVISO] El dominio {$domain} vence en {$left} días";
        $panel = (string) config('newdesk.frontend_url');
        $html = sprintf('<div style="font-family:system-ui,sans-serif;font-size:15px;color:#111"><p><strong>%s</strong> (proyecto %s de %s) vence el <strong>%s</strong>.</p><p><a href="%s">Abrir el panel</a></p></div>',
            e($domain), e($project->name), e($project->client->name), $expires->format('d/m/Y'), e($panel));

        Mail::to($recipient)->queue(new StatusPageMail($subject, $html));
        Project::withoutTimestamps(fn () => $project->forceFill(['domain_notice_level' => $level])->save());
    }

    /** NIC Argentina's RDAP does not answer reliably: .ar domains usually fall back to the manual date. */
    private function rdap(string $domain): ?CarbonImmutable
    {
        $response = Http::timeout(15)->accept('application/rdap+json')->get('https://rdap.org/domain/'.strtolower($domain));
        if ($response->status() !== 200) {
            throw new RuntimeException("rdap: HTTP {$response->status()}");
        }
        foreach ($response->json('events') ?? [] as $event) {
            if (($event['eventAction'] ?? null) === 'expiration' && isset($event['eventDate'])) {
                return CarbonImmutable::parse($event['eventDate'])->utc();
            }
        }

        throw new RuntimeException('rdap: no expiration event');
    }
}
