<?php

use App\Domain\Monitoring\Contracts\DiscordGateway;
use App\Domain\Monitoring\Discord\DiscordError;
use App\Domain\Monitoring\Discord\DiscordMessage;
use App\Domain\Monitoring\Jobs\SendDiscordMessage;
use App\Infrastructure\Discord\DiscordBotGateway;
use App\Infrastructure\Discord\MutedDiscordGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeDiscord;

/**
 * Port of monitoreo's discord/discord_test.go, escape_test.go and priority_test.go: the bot's REST
 * calls against a fake Discord (RF-13.09, D-059, D-060, D-078, D-107).
 */
beforeEach(function () {
    Http::preventStrayRequests();
    $this->fake = FakeDiscord::install();
    $this->discord = app(DiscordGateway::class);
});

it('only pings the given users and clips what does not fit (D-059)', function () {
    $this->discord->send(new DiscordMessage(FakeDiscord::PLAYINN, str_repeat('x', 2500).' @everyone', ['1403351571303104612']));

    [$post] = $this->fake->take();
    expect($post['channel'])->toBe(FakeDiscord::PLAYINN)
        ->and(mb_strlen($post['content']))->toBe(DiscordMessage::MAX_CONTENT)
        ->and(mb_substr($post['content'], -1))->toBe('…')
        ->and($post['allowed_mentions'])->toBe(['parse' => [], 'users' => ['1403351571303104612']]);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bot '.FakeDiscord::TOKEN));

    expect(fn () => $this->discord->send(new DiscordMessage('../../guilds', 'x')))->toThrow(DiscordError::class, 'id de canal inválido');
});

it('hands a rate-limited post back to the queue after Discord\'s retry_after, and drops other failures', function () {
    $this->fake->limitNext = true;
    $job = (new SendDiscordMessage(new DiscordMessage(FakeDiscord::PLAYINN, 'hola')))->withFakeQueueInteractions();
    $job->handle($this->discord);
    $job->assertReleased(1); // 0.2 s, rounded up
    expect($this->fake->take())->toBe([]);

    $job = (new SendDiscordMessage(new DiscordMessage(FakeDiscord::PLAYINN, 'hola')))->withFakeQueueInteractions();
    $job->handle($this->discord);
    $job->assertNotReleased()->assertNotFailed();
    expect($this->fake->take())->toHaveCount(1);

    // A channel Discord no longer has: logged and dropped, as monitoreo did.
    $this->fake->missing = [FakeDiscord::PLAYINN];
    $job = (new SendDiscordMessage(new DiscordMessage(FakeDiscord::PLAYINN, 'hola')))->withFakeQueueInteractions();
    $job->handle($this->discord);
    $job->assertNotReleased()->assertNotFailed();
});

it('words its errors for the team and never shows the token', function () {
    $wrongGuild = new DiscordBotGateway(FakeDiscord::TOKEN, '1111111111111111111', '', [FakeDiscord::CATEGORY]);
    try {
        $wrongGuild->channels();
        $this->fail('a missing guild must fail');
    } catch (DiscordError $e) {
        expect($e->status)->toBe(404)->and($e->getMessage())->toBe('Discord respondió 404: ese canal no existe')
            ->and($e->getMessage())->not->toContain(FakeDiscord::TOKEN);
    }

    expect(fn () => (new MutedDiscordGateway)->send(new DiscordMessage(FakeDiscord::PLAYINN, 'x')))
        ->toThrow(DiscordError::class, 'El bot de Discord no está configurado.')
        ->and((new MutedDiscordGateway)->configured())->toBeFalse();
});

it('creates a channel under the category, inheriting its permissions', function () {
    $created = $this->discord->createChannel('playinn', 'Avisos de Monitoreo · Playinn');

    expect($created)->toBe(['id' => FakeDiscord::CREATED, 'name' => 'playinn'])
        ->and($this->fake->created)->toBe([['name' => 'playinn', 'type' => 0, 'topic' => 'Avisos de Monitoreo · Playinn', 'parent_id' => FakeDiscord::CATEGORY]]);
});

it('tells where the bot can post, and only knows the allowed groups (D-060)', function () {
    $channels = collect($this->discord->channels())->keyBy('name');

    // By group, then name (byte order, as monitoreo).
    expect($channels->map->can_send->all())->toBe([
        'playinn' => true,     // private, the bot's role is allowed
        'reaquila' => false,   // private, the bot's role is not allowed
        'Monitoring' => true,  // thread under #general
        'general' => true,     // @everyone can
        'solo-leer' => false,  // the bot itself is denied Send
    ])
        ->and($channels['Monitoring']['kind'])->toBe('thread')
        ->and($channels['playinn']['category'])->toBe('Equipo BBG1')
        ->and($this->discord->allowed('1000000000000000009'))->toBeFalse() // a hidden channel
        ->and($this->discord->allowed(FakeDiscord::PLAYINN))->toBeTrue();
});

it('lists the members of the server, bots left out', function () {
    expect($this->discord->members())->toBe([
        ['id' => '1403351571303104612', 'username' => 'nexo_violet', 'display_name' => 'Iña Telayna ( Team Violet)'],
        ['id' => '1077787097219473408', 'username' => 'orlando85.', 'display_name' => 'Orlando'],
    ]);
});

it('escapes masked links, tokens, parentheses and mentions (D-078, CA-13.21)', function () {
    expect(DiscordMessage::escape('[x](https://e.invalid) <@1> # a'))->toBe('\[x\]\(https://e.invalid\) \<\@1\> \# a')
        ->and(DiscordMessage::escape('app(x)@everyone'))->toBe('app\(x\)\@everyone');
});

it('sends server-wide notices on the queue the Discord worker drains first (D-107)', function () {
    Queue::fake();
    foreach (range(1, 3) as $i) {
        SendDiscordMessage::dispatch(new DiscordMessage(FakeDiscord::PLAYINN, "pm2 {$i}"));
    }
    SendDiscordMessage::dispatch(new DiscordMessage(FakeDiscord::SUMMARY, 'servidor caído', priority: true));

    Queue::assertPushedOn('discord', SendDiscordMessage::class, fn (SendDiscordMessage $job) => $job->message->content === 'pm2 1');
    Queue::assertPushedOn('discord-priority', SendDiscordMessage::class, fn (SendDiscordMessage $job) => $job->message->content === 'servidor caído');
});
