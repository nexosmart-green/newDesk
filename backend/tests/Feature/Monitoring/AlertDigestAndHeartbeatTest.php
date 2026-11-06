<?php

use App\Domain\Monitoring\Actions\ApplyAlertResults;
use App\Domain\Monitoring\Actions\SendAlertDigest;
use App\Domain\Monitoring\Alerts\AlertObservation;
use App\Domain\Monitoring\Alerts\AlertResult;
use App\Domain\Monitoring\Alerts\AlertRules;
use App\Domain\Monitoring\Discord\DiscordMessage;
use App\Domain\Monitoring\Events\AlertChanged;
use App\Domain\Monitoring\Polling\PollerSignals;
use App\Domain\StatusPage\Notifications\StatusPageMail;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeDiscord;
use Tests\Support\StatusPageFixtures as Fixtures;

/**
 * Port of monitoreo's alerts/digest_test.go (D-046, D-059, D-061) and its heartbeat loop (D-009):
 * the 9 and 18 h digest by mail and in #resumen, and the external ping (RF-13.09).
 */
beforeEach(function () {
    Http::preventStrayRequests();
    Mail::fake();
    Event::fake([AlertChanged::class]); // the digest only reads what the engine stored
    config(['newdesk.monitoring.notify' => true, 'newdesk.frontend_url' => 'https://x', 'newdesk.monitoring.digest_email_to' => 'x@y.com, z@y.com']);
    $this->admin = provisionCompany();
});

it('finds the last 9 or 18 h cut, in Argentina (D-046)', function () {
    $at = fn (int $day, int $hour, int $minute) => CarbonImmutable::create(2026, 10, $day, $hour, $minute, 0, SendAlertDigest::TIMEZONE);
    foreach ([
        [$at(13, 8, 59), $at(12, 18, 0)],
        [$at(13, 9, 0), $at(13, 9, 0)],
        [$at(13, 17, 59), $at(13, 9, 0)],
        [$at(13, 18, 0), $at(13, 18, 0)],
        [$at(13, 23, 30), $at(13, 18, 0)],
        [$at(14, 0, 30), $at(13, 18, 0)],
    ] as [$now, $cut]) {
        expect(SendAlertDigest::lastCut($now->utc())->eq($cut))->toBeTrue("{$now}");
    }

    $digest = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'monitoring:digest'));
    expect($digest->expression)->toBe('0 9,18 * * *')->and($digest->timezone)->toBe(SendAlertDigest::TIMEZONE);
});

// TestDigestGroupsWindowAndOpenAlerts
it('mails and posts what opened or resolved in the window plus what is still open (D-046, D-061)', function () {
    $discord = FakeDiscord::install();
    $server = Fixtures::server($this->admin, 'v8');
    $apply = fn (CarbonImmutable $at, AlertResult ...$results) => app(ApplyAlertResults::class)($server, $results, $at);
    $from = CarbonImmutable::create(2026, 10, 13, 9, 0, 0, SendAlertDigest::TIMEZONE);
    $to = $from->addHours(9);

    app(SendAlertDigest::class)->send($from, $to);
    Mail::assertNothingQueued(); // nothing happened and nothing is open

    $restart = fn (bool $firing) => new AlertResult(AlertRules::PM2_RESTARTS, 0, 1, [new AlertObservation('api#0@deploy', $firing, AlertRules::WARNING, 'PM2: api reinició', '1')]);
    // Before the window: still open, so it shows under "Sigue abierto" only.
    $apply($from->subHours(2), new AlertResult(AlertRules::DISK, 0, 1, [new AlertObservation('/', true, AlertRules::WARNING, 'Disco / alto', '88')]));
    // Three restarts inside the window, the last one still open.
    foreach ([10, 70, 400] as $i => $minute) {
        $apply($from->addMinutes($minute), $restart(true));
        if ($i < 2) {
            $apply($from->addMinutes($minute + 15), $restart(false));
        }
    }
    // After the window: not part of it.
    $apply($to->addHour(), ...AlertRules::serverStatus('agent_down', 22));

    $this->travelTo($to->addSeconds(30));
    Artisan::call('monitoring:digest');

    $mail = null;
    Mail::assertQueued(StatusPageMail::class, function (StatusPageMail $queued) use (&$mail) {
        $mail = $queued;

        return true;
    });
    expect($mail->mailSubject)->toBe('[RESUMEN] 9 a 18 h: 3 eventos, 3 abiertas')
        ->and($mail->hasTo('x@y.com') && $mail->hasTo('z@y.com'))->toBeTrue()
        ->and($mail->body)->toContain('<h3>Sigue abierto</h3>', 'Disco / alto (advertencia, desde 13/10 07:00)', '<h3>En el período</h3>',
            'PM2: api reinició: 3 veces, 13/10 09:10 a 13/10 15:40, abierta', 'href="https://x/monitoreo/alerts"')
        ->and($mail->body)->not->toContain('Disco / alto: '); // opened before the window: not an event

    // The same digest in #resumen, without pings.
    $posts = $discord->take();
    expect($posts)->toHaveCount(1)->and($posts[0]['channel'])->toBe(FakeDiscord::SUMMARY)->and($posts[0]['mentions'])->toBe([])
        ->and($posts[0]['content'])->toStartWith('📋 **Resumen de 9 a 18 h** · 3 eventos, 3 abiertas')
        ->toContain('__Sigue abierto__', '**v8**', '• PM2: api reinició: 3 veces')->toEndWith("\n<https://x/monitoreo/alerts>");
});

// TestDiscordDigestKeepsTheLinkWhenItIsLong
it('fits a long digest in one Discord message and keeps the link', function () {
    $lines = array_map(fn (int $i) => "• alerta número {$i} con un texto largo", range(0, 299));
    $chat = SendAlertDigest::chat('📋 **Resumen**', $lines, 'https://x/alerts');

    expect(mb_strlen($chat))->toBeLessThanOrEqual(DiscordMessage::MAX_CONTENT)
        ->and($chat)->toEndWith("líneas más en el panel\n<https://x/alerts>");
});

it('pings the heartbeat every minute only while the poller is healthy (D-009)', function () {
    config(['newdesk.monitoring.healthchecks_url' => 'https://hc-ping.test/uuid']);
    Http::fake(['https://hc-ping.test/*' => Http::response('OK')]);
    $ping = fn () => Artisan::call('monitoring:heartbeat');

    $ping(); // the poller never ticked
    Http::assertNothingSent();

    app(PollerSignals::class)->markTick(now()->getTimestamp());
    $ping();
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'https://hc-ping.test/uuid');

    $this->travel(PollerSignals::TICK_STALE_AFTER)->seconds();
    $ping();
    Http::assertSentCount(1);
});
