<?php

namespace App\Domain\Monitoring\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Discord\Notifier;
use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Queries\ProjectErrors;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Every 5 min (monitoreo portal.ErrorWatch, D-054): reads the /api/errors of every project that has
 * a Discord channel and posts the `ERROR` entries of the day it had not seen, grouped. What it saw
 * lives in the cache: the first read of a project (or of a changed endpoint) only takes note.
 * Nothing is read while notices are off (CA-13.20): monitoreo is the one reading them then.
 */
final class WatchProjectErrors
{
    /** A burst of errors keeps posting, but pings the lead at most this often. */
    private const PING_EVERY = 3600;

    /** The endpoint bans an IP for 24 h after 10 rejected tokens in an hour: a bad token waits. */
    private const AUTH_PAUSE = 6 * 3600;

    /** A project that stops being watched is forgotten on its own. */
    private const STATE_TTL = 2 * 86400;

    public function __construct(private readonly Notifier $notifier, private readonly ProjectErrors $errors) {}

    public function __invoke(): void
    {
        if (! $this->notifier->enabled()) {
            return;
        }
        $now = CarbonImmutable::now()->getTimestamp();
        foreach ($this->notifier->channels() as $channel) {
            $project = $channel['project'];
            $token = $project->getRawOriginal('errors_token');
            if ($project->errors_url === null || $token === null) {
                continue;
            }
            $key = "monitoring:errorwatch:{$project->id}";
            $config = hash('sha256', $project->errors_url.'|'.$token); // a new URL or token takes a new baseline
            $state = Cache::get($key);
            if (! is_array($state) || $state['config'] !== $config) {
                $state = ['config' => $config, 'day' => '', 'seen' => null, 'paused_until' => 0, 'failing' => false, 'last_ping' => 0];
            }
            if ($now < $state['paused_until']) {
                continue;
            }

            $day = $this->errors->today($project);
            if (is_string($day)) {
                if (ProjectErrors::rejected($day)) {
                    $state['paused_until'] = $now + self::AUTH_PAUSE;
                    Log::warning('errors watch: token rejected, paused', ['project' => $project->slug, 'error' => $day]);
                } elseif (! $state['failing']) {
                    Log::warning('errors watch: fetch failed', ['project' => $project->slug, 'error' => $day]);
                }
                $state['failing'] = true;
            } else {
                $state['failing'] = false;
                $state = $this->observe($channel, $state, $day, $now);
            }
            Cache::put($key, $state, self::STATE_TTL);
        }
    }

    /**
     * @param  array{project: Project, channel: string, lead: User|null}  $channel
     * @param  array<string, mixed>  $state
     * @param  array{date: string, items: list<array<string, mixed>>}  $day
     * @return array<string, mixed>
     */
    private function observe(array $channel, array $state, array $day, int $now): array
    {
        $baseline = $state['seen'] === null;
        if ($baseline || $day['date'] !== $state['day']) {
            [$state['day'], $state['seen']] = [$day['date'], []];
        }
        $fresh = [];
        foreach ($day['items'] as $item) {
            $seen = hash('xxh128', $item['timestamp'].'|'.$item['message'].'|'.$item['url']);
            if (strcasecmp($item['level'], 'ERROR') !== 0 || isset($state['seen'][$seen])) {
                continue;
            }
            $state['seen'][$seen] = true;
            $fresh[] = $item;
        }
        if ($baseline || $fresh === []) {
            return $state;
        }
        $ping = $now - $state['last_ping'] >= self::PING_EVERY;
        if ($ping) {
            $state['last_ping'] = $now;
        }
        $this->notifier->post($this->notifier->errorsMessage($channel, $fresh, $ping));

        return $state;
    }
}
