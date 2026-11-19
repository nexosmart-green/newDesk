<?php

use App\Domain\Assistant\Discord\Gateway\GatewayAction;
use App\Domain\Assistant\Discord\Gateway\GatewaySession;

/** RF-14.08 phase 5: the gateway protocol behind the assistant's DMs, frame by frame. */
function gatewayActionTypes(array $actions): array
{
    return array_map(fn (GatewayAction $action) => $action->type, $actions);
}

function gatewayReady(GatewaySession $session): void
{
    $session->receive(['op' => 10, 'd' => ['heartbeat_interval' => 41250]]);
    $session->receive(['op' => 0, 's' => 1, 't' => 'READY', 'd' => ['session_id' => 'abc', 'resume_gateway_url' => 'wss://resume.discord.gg/']]);
}

it('identifies on HELLO with only the DM intent and starts beating at Discord\'s interval', function () {
    $session = new GatewaySession('BOT-TOKEN');
    expect($session->url())->toBe(GatewaySession::DEFAULT_URL);

    [$heartbeat, $identify] = $session->receive(['op' => 10, 'd' => ['heartbeat_interval' => 41250]]);

    expect($heartbeat->type)->toBe(GatewayAction::HEARTBEAT)->and($heartbeat->intervalMs)->toBe(41250)
        ->and($identify->payload['op'])->toBe(2)
        ->and($identify->payload['d']['token'])->toBe('BOT-TOKEN')
        ->and($identify->payload['d']['intents'])->toBe(4096);
});

it('beats with the last sequence, and treats a beat without ACK as a dead connection', function () {
    $session = new GatewaySession('BOT-TOKEN');
    gatewayReady($session);
    $session->receive(['op' => 0, 's' => 7, 't' => 'TYPING_START', 'd' => []]);

    expect($session->beat()[0]->payload)->toBe(['op' => 1, 'd' => 7]);
    $session->receive(['op' => 11]);
    expect(gatewayActionTypes($session->beat()))->toBe([GatewayAction::SEND]); // ACKed: beats again
    expect(gatewayActionTypes($session->beat()))->toBe([GatewayAction::RECONNECT]); // no ACK: zombie

    // Discord asking for a beat gets one right away.
    expect($session->receive(['op' => 1])[0]->payload)->toBe(['op' => 1, 'd' => 7]);
});

it('resumes after a reconnect where it left, and identifies again when the session is gone', function () {
    $session = new GatewaySession('BOT-TOKEN');
    gatewayReady($session);
    $session->receive(['op' => 0, 's' => 12, 't' => 'MESSAGE_CREATE', 'd' => ['id' => '1']]);

    expect(gatewayActionTypes($session->receive(['op' => 7])))->toBe([GatewayAction::RECONNECT])
        ->and($session->url())->toBe('wss://resume.discord.gg/?v=10&encoding=json');
    $resume = $session->receive(['op' => 10, 'd' => ['heartbeat_interval' => 41250]])[1]->payload;
    expect($resume)->toBe(['op' => 6, 'd' => ['token' => 'BOT-TOKEN', 'session_id' => 'abc', 'seq' => 12]]);

    // Resumable invalid session: resume again. Not resumable: start over.
    $session->receive(['op' => 9, 'd' => true]);
    expect($session->url())->toStartWith('wss://resume.discord.gg');
    $session->receive(['op' => 9, 'd' => false]);
    expect($session->url())->toBe(GatewaySession::DEFAULT_URL)
        ->and($session->receive(['op' => 10, 'd' => ['heartbeat_interval' => 1000]])[1]->payload['op'])->toBe(2);
});

it('hands every event but READY to the app', function () {
    $session = new GatewaySession('BOT-TOKEN');
    gatewayReady($session);

    [$event] = $session->receive(['op' => 0, 's' => 2, 't' => 'MESSAGE_CREATE', 'd' => ['content' => 'hola']]);

    expect($event->type)->toBe(GatewayAction::DISPATCH)->and($event->event)->toBe('MESSAGE_CREATE')->and($event->payload)->toBe(['content' => 'hola'])
        ->and($session->receive(['op' => 99]))->toBe([]);
});

it('stops for good on a bad token or intents, starts over on a bad sequence, and resumes otherwise', function () {
    $session = new GatewaySession('BOT-TOKEN');
    gatewayReady($session);

    expect($session->closed(4004)[0]->type)->toBe(GatewayAction::STOP)
        ->and($session->closed(4014)[0]->reason)->toContain('4014');

    expect(gatewayActionTypes($session->closed(1006)))->toBe([GatewayAction::RECONNECT])
        ->and($session->url())->toStartWith('wss://resume.discord.gg');
    $session->closed(4007);
    expect($session->url())->toBe(GatewaySession::DEFAULT_URL);
});
