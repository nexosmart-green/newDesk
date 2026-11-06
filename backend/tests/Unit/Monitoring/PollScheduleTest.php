<?php

use App\Domain\Monitoring\Agent\Section;
use App\Domain\Monitoring\Polling\PollSchedule;

/** @return array{id: int, enabled: bool, status: string, credentials: string} */
function scheduledServer(int $id, string $status = 'online', string $credentials = 'a'): array
{
    return ['id' => $id, 'enabled' => true, 'status' => $status, 'credentials' => $credentials];
}

/**
 * One poller tick where every started task finishes at once.
 *
 * @param  list<string>  $failing  sections that fail
 * @return list<string> "id|task" started
 */
function runTick(PollSchedule $schedule, int $now, array $failing = []): array
{
    $schedule->collect($now);
    $started = [];
    while (($batch = $schedule->startable()) !== []) {
        foreach ($batch as [$id, $task]) {
            $started[] = "{$id}|{$task}";
            if (in_array($task, $failing, true)) {
                $schedule->sectionFailed($id, $task, $now);
            } elseif ($task !== Section::HEALTH) {
                $schedule->sectionSucceeded($id, $task);
            }
            $schedule->finish($id, $task);
        }
    }

    return $started;
}

it('has a polling interval for every section (RF-13.02)', function () {
    foreach (Section::ALL as $section) {
        expect(PollSchedule::INTERVALS)->toHaveKey($section);
    }
});

it('waits for the agent to be online before polling sections, then follows each interval', function () {
    $schedule = new PollSchedule;
    $schedule->sync([scheduledServer(1, 'unknown')], 0);

    // The site check does not need the agent (RF-13.05).
    expect(runTick($schedule, 0))->toBe(['1|health', '1|sitecheck']);

    $schedule->setStatus(1, 'online');
    expect(runTick($schedule, 1))->toHaveCount(count(Section::ALL));
    expect(runTick($schedule, 2))->toBe([]);
    expect(runTick($schedule, 30))->toBe(['1|health']);
    expect(runTick($schedule, 61))->toContain('1|metrics', '1|services', '1|pm2', '1|supervisor')->not->toContain('1|packages');
    expect(runTick($schedule, 300))->toContain('1|sitecheck');
});

it('lets one server hold 2 of the 6 shared slots, while health and site checks take none (CA-13.05)', function () {
    $schedule = new PollSchedule;
    $schedule->sync([scheduledServer(1), scheduledServer(2), scheduledServer(3), scheduledServer(4)], 0);
    $schedule->collect(0);

    // Nothing finishes: the slow servers keep their slots.
    $running = $schedule->startable();
    $checks = array_filter($running, fn (array $task) => in_array($task[1], [Section::HEALTH, PollSchedule::SITECHECK], true));
    $perServer = array_count_values(array_map(fn (array $task) => $task[0], array_diff_key($running, $checks)));

    expect(array_sum($perServer))->toBe(PollSchedule::SHARED_SLOTS)
        ->and(max($perServer))->toBe(PollSchedule::SLOTS_PER_SERVER)
        ->and($checks)->toHaveCount(8)
        ->and($schedule->startable())->toBe([]);

    // Every slot is taken, yet a health check that comes due runs right away.
    foreach ($checks as [$id, $task]) {
        $schedule->finish($id, $task);
    }
    $schedule->collect(30);
    $healthAgain = $schedule->startable();
    expect($healthAgain)->toEqual([[1, 'health'], [2, 'health'], [3, 'health'], [4, 'health']]);

    // Once the slow calls end, the waiting tasks get their slots; nothing queued is lost.
    foreach ([...$healthAgain, ...array_diff_key($running, $checks)] as [$id, $task]) {
        $schedule->finish($id, $task);
    }
    $sections = PollSchedule::SHARED_SLOTS;
    while (($batch = $schedule->startable()) !== []) {
        $sections += count($batch);
        foreach ($batch as [$id, $task]) {
            $schedule->finish($id, $task);
        }
    }
    expect($sections)->toBe(4 * count(Section::ALL))->and($schedule->busy())->toBeFalse();
});

it('retries a failed section after 2 min doubling each time, never later than its interval (CA-13.04)', function () {
    $schedule = new PollSchedule;
    $schedule->sync([scheduledServer(1)], 0);
    $hits = 0;
    $tick = function (int $minute, bool $fails = true) use ($schedule, &$hits) {
        $started = runTick($schedule, $minute * 60, $fails ? ['packages'] : []);
        $hits += (int) in_array('1|packages', $started, true);

        return $hits;
    };

    expect($tick(0))->toBe(1)       // first poll fails
        ->and($tick(1))->toBe(1)    // before the retry delay
        ->and($tick(2))->toBe(2)    // retry after 2 min
        ->and($tick(5))->toBe(2)    // the second retry waits 4 min
        ->and($tick(6))->toBe(3)
        ->and($tick(14, false))->toBe(4)        // third retry after 8 min, now served
        ->and($tick(134, false))->toBe(4)       // success goes back to the 12 h interval
        ->and($tick(14 + 12 * 60))->toBe(5)     // regular poll after 12 h, fails
        ->and($tick(16 + 12 * 60))->toBe(6);    // a success had reset the backoff: 2 min again

    // The delay never goes past the interval: services (1 min) retries every minute.
    $schedule->sectionFailed(1, 'services', 1000);
    $schedule->sectionFailed(1, 'services', 1000);
    $schedule->sectionFailed(1, 'services', 1000);
    expect(runTick($schedule, 1060))->toContain('1|services');
});

it('polls metrics every 5 s while live and goes back to 1 min after (CA-13.06)', function () {
    $schedule = new PollSchedule;
    $schedule->sync([scheduledServer(1)], 0);
    runTick($schedule, 0);

    $schedule->setLive([1], 10); // first beat: metrics now, not at 60 s
    expect(runTick($schedule, 10))->toBe(['1|metrics'])
        ->and(runTick($schedule, 14))->toBe([])
        ->and(runTick($schedule, 15))->toBe(['1|metrics']);

    $schedule->setLive([], 16);
    expect(runTick($schedule, 20))->toBe(['1|metrics']) // the run already scheduled at +5 s
        ->and(runTick($schedule, 30))->toBe(['1|health'])
        ->and(runTick($schedule, 79))->not->toContain('1|metrics')
        ->and(runTick($schedule, 80))->toContain('1|metrics');
});

it('keeps one raw metric point per 55 s', function () {
    $schedule = new PollSchedule;
    $schedule->sync([scheduledServer(1)], 0);

    expect($schedule->takeRawMetric(1, 100))->toBeTrue()
        ->and($schedule->takeRawMetric(1, 105))->toBeFalse()
        ->and($schedule->takeRawMetric(1, 154))->toBeFalse()
        ->and($schedule->takeRawMetric(1, 155))->toBeTrue();
});

it('starts a server over when its credentials change, and forgets a deleted one', function () {
    $schedule = new PollSchedule;
    $schedule->sync([scheduledServer(1)], 0);
    runTick($schedule, 0, ['packages']);
    $schedule->healthFailed(1);

    $schedule->sync([scheduledServer(1, credentials: 'b')], 10);
    expect(runTick($schedule, 10))->toHaveCount(count(Section::ALL) + 2)
        ->and($schedule->healthFailed(1))->toBe(1);

    $schedule->sync([], 20);
    expect($schedule->serverIds())->toBe([])->and(runTick($schedule, 100))->toBe([]);
});

it('runs a forced refresh at once and asks for a reload when the server is unknown', function () {
    $schedule = new PollSchedule;
    $schedule->sync([scheduledServer(1)], 0);
    runTick($schedule, 0);

    expect($schedule->trigger(1, 'packages', 5))->toBeTrue()
        ->and(runTick($schedule, 5))->toBe(['1|packages'])
        ->and($schedule->trigger(1, null, 6))->toBeTrue()
        ->and(runTick($schedule, 6))->toHaveCount(count(Section::ALL) + 2)
        ->and($schedule->trigger(99, null, 7))->toBeFalse();
});
