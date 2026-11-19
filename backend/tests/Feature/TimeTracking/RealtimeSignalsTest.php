<?php

use App\Domain\Shared\Realtime\UserSignal;
use App\Domain\TimeTracking\Actions\StopDayClock;
use App\Domain\TimeTracking\Models\DayClock;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/TimeTrackingHelpers.php';
require_once __DIR__.'/../Tasks/ParityHelpers.php';

/** @return list<string> kinds signalled to that person, in order */
function signalsTo(int $userId): array
{
    return Event::dispatched(UserSignal::class)
        ->map(fn (array $call) => $call[0])
        ->filter(fn (UserSignal $signal) => $signal->userId === $userId)
        ->map(fn (UserSignal $signal) => $signal->kind)
        ->values()->all();
}

it('lets each person subscribe only to their own channel (RNF-12)', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    require base_path('routes/channels.php');
    $admin = adminUser();
    $dev = userWithRole($admin);
    $channel = fn (int $userId) => ['socket_id' => '1234.5678', 'channel_name' => 'private-'.UserSignal::channelName($userId)];

    $this->actingAs($dev)->postJson('/api/v1/broadcasting/auth', $channel($dev->id))->assertOk()->assertJsonStructure(['auth']);
    $this->actingAs($dev)->postJson('/api/v1/broadcasting/auth', $channel($admin->id))->assertForbidden();
});

it('tells only the owner when their timer starts and stops, from wherever it changed', function () {
    $admin = adminUser();
    [$dev, $mate] = [userWithRole($admin), userWithRole($admin)];
    ['task' => $task] = bagProject($admin, [$dev, $mate], 10 * 3600);
    Event::fake([UserSignal::class]);

    startTimer($dev, $task)->assertOk();
    $this->travel(10)->minutes();
    stopTimer($dev)->assertOk();

    expect(signalsTo($dev->id))->not->toBeEmpty()->each->toBe(UserSignal::TIMER)
        ->and(signalsTo($mate->id))->toBe([]);
    Event::assertDispatched(UserSignal::class, fn (UserSignal $signal) => $signal->broadcastOn()[0]->name === "private-user.{$dev->id}"
        && $signal->broadcastAs() === UserSignal::TIMER && $signal->broadcastWith() === []);
});

it('tells the person when the minute job closes their day clock (a mass update, no model events)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $this->actingAs($dev)->postJson('/api/v1/me/day-clock')->assertSuccessful();
    Event::fake([UserSignal::class]);

    $this->travel(2)->days();
    expect(app(StopDayClock::class)->overdue(now()))->toBe(1)
        ->and(DayClock::query()->withoutGlobalScopes()->whereNull('ended_at')->count())->toBe(0)
        ->and(signalsTo($dev->id))->toBe([UserSignal::TIMER]);
});

it('sends one signal per commit and kind, however many of the person\'s entries changed', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 10 * 3600);
    Event::fake([UserSignal::class]);

    DB::transaction(function () use ($dev, $task) {
        foreach ([1, 2, 3] as $hour) {
            logEntry($dev, $task, now()->subHours($hour), 600);
        }
    });
    expect(signalsTo($dev->id))->toBe([UserSignal::TIMER]);

    logEntry($dev, $task, now()->subHours(5), 600);
    expect(signalsTo($dev->id))->toBe([UserSignal::TIMER, UserSignal::TIMER]);
});

it('rings the bell of the person who got a notification, and nobody else', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    Event::fake([UserSignal::class]);
    $notification = new class extends Notification {};

    event(new NotificationSent($dev, $notification, 'database'));
    event(new NotificationSent($dev, $notification, 'mail'));

    expect(signalsTo($dev->id))->toBe([UserSignal::NOTIFICATIONS])
        ->and(signalsTo($admin->id))->toBe([]);
});
