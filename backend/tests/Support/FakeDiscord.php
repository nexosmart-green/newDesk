<?php

namespace Tests\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Discord's REST API as the bot uses it (monitoreo's fakeAPI and fakeDiscord): configures the bot,
 * answers the routes like Discord and records every message and channel the bot creates.
 */
final class FakeDiscord
{
    public const GUILD = '1336761121088077915';

    public const BOT = '1560290097713254450';

    public const BOT_ROLE = '1560000000000000900';

    /** "Equipo BBG1": where the bot creates channels. */
    public const CATEGORY = '1336761249484247083';

    public const GENERAL = '1336761121557712986';

    /** A group outside the allowed ones. */
    public const HIDDEN = '1457189557828059167';

    /** #resumen */
    public const SUMMARY = '1000000000000000100';

    /** "playinn": private, the bot's role may post. */
    public const PLAYINN = '1000000000000000003';

    public const CREATED = '1560999999999999001';

    public const TOKEN = 'test-token';

    /** @var list<array{channel: string, content: string, mentions: list<string>, allowed_mentions: array<string, mixed>}> */
    public array $posts = [];

    /** @var list<array<string, mixed>> bodies of the channels created */
    public array $created = [];

    /** @var list<string> channel ids Discord answers 404 for */
    public array $missing = [];

    public bool $limitNext = false;

    public int $requests = 0;

    /** @param  list<string>  $categories  DISCORD_CHANNEL_CATEGORIES */
    public static function install(array $categories = [self::CATEGORY, self::GENERAL]): self
    {
        config(['newdesk.monitoring.discord' => ['bot_token' => self::TOKEN, 'guild_id' => self::GUILD, 'category_id' => self::CATEGORY,
            'summary_channel_id' => self::SUMMARY, 'channel_categories' => $categories]]);
        $fake = new self;
        Http::fake(['discord.com/api/v10/*' => fn (Request $request) => $fake->answer($request)]);

        return $fake;
    }

    /** What was posted since the last call. @return list<array{channel: string, content: string, mentions: list<string>, allowed_mentions: array<string, mixed>}> */
    public function take(): array
    {
        [$posts, $this->posts] = [$this->posts, []];

        return $posts;
    }

    public function answer(Request $request): mixed
    {
        $this->requests++;
        if ($request->header('Authorization') !== ['Bot '.self::TOKEN]) {
            return Http::response('', 401);
        }
        if ($this->limitNext) {
            $this->limitNext = false;

            return Http::response(['retry_after' => 0.2, 'global' => false], 429);
        }
        $path = Str::after(parse_url($request->url(), PHP_URL_PATH), '/api/v10');
        $guild = '/guilds/'.self::GUILD;

        if ($request->method() === 'POST' && preg_match('#^/channels/(\d+)/messages$#', $path, $match)) {
            if (in_array($match[1], $this->missing, true)) {
                return Http::response('', 404);
            }
            $this->posts[] = ['channel' => $match[1], 'content' => $request['content'], 'mentions' => $request['allowed_mentions']['users'] ?? [],
                'allowed_mentions' => $request['allowed_mentions']];

            return Http::response(['id' => '1']);
        }
        if ($request->method() === 'POST' && $path === "{$guild}/channels") {
            $this->created[] = $request->data();

            return Http::response(['id' => self::CREATED, 'name' => $request['name']]);
        }

        return match ($path) {
            "{$guild}/channels" => Http::response([
                ['id' => self::CATEGORY, 'name' => 'Equipo BBG1', 'type' => 4, 'permission_overwrites' => []],
                ['id' => self::GENERAL, 'name' => 'General', 'type' => 4, 'permission_overwrites' => []],
                ['id' => self::HIDDEN, 'name' => 'self', 'type' => 4, 'permission_overwrites' => []],
                ['id' => '1000000000000000001', 'name' => 'general', 'type' => 0, 'parent_id' => self::GENERAL, 'permission_overwrites' => []],
                ['id' => '1000000000000000009', 'name' => 'fuera-de-grupos', 'type' => 0, 'parent_id' => self::HIDDEN, 'permission_overwrites' => []],
                ['id' => '1000000000000000010', 'name' => 'sin-grupo', 'type' => 0, 'parent_id' => null, 'permission_overwrites' => []],
                ['id' => '1000000000000000002', 'name' => 'reaquila', 'type' => 0, 'parent_id' => self::CATEGORY,
                    'permission_overwrites' => [['id' => self::GUILD, 'type' => 0, 'allow' => '0', 'deny' => '1024']]],
                ['id' => self::PLAYINN, 'name' => 'playinn', 'type' => 0, 'parent_id' => self::CATEGORY, 'permission_overwrites' => [
                    ['id' => self::GUILD, 'type' => 0, 'allow' => '0', 'deny' => '1024'], ['id' => self::BOT_ROLE, 'type' => 0, 'allow' => '3072', 'deny' => '0']]],
                ['id' => '1000000000000000004', 'name' => 'solo-leer', 'type' => 0, 'parent_id' => self::GENERAL,
                    'permission_overwrites' => [['id' => self::BOT, 'type' => 1, 'allow' => '0', 'deny' => '2048']]],
                ['id' => '1000000000000000005', 'name' => 'Voz', 'type' => 2, 'permission_overwrites' => []],
            ]),
            "{$guild}/threads/active" => Http::response(['threads' => [
                ['id' => '1555581974394048664', 'name' => 'Monitoring', 'type' => 12, 'parent_id' => '1000000000000000001'],
                ['id' => '1555581974394048665', 'name' => 'hilo-oculto', 'type' => 12, 'parent_id' => '1000000000000000009'],
            ], 'members' => []]),
            "{$guild}/roles" => Http::response([['id' => self::GUILD, 'permissions' => '274877910016'], ['id' => self::BOT_ROLE, 'permissions' => '0']]),
            '/users/@me' => Http::response(['id' => self::BOT]),
            "{$guild}/members/".self::BOT => Http::response(['roles' => [self::BOT_ROLE]]),
            "{$guild}/members" => Http::response([
                ['nick' => null, 'user' => ['id' => '1403351571303104612', 'username' => 'nexo_violet', 'global_name' => 'Iña Telayna ( Team Violet)']],
                ['nick' => 'Orlando', 'user' => ['id' => '1077787097219473408', 'username' => 'orlando85.', 'global_name' => null]],
                ['user' => ['id' => self::BOT, 'username' => 'Monitoreo', 'bot' => true]],
            ]),
            default => Http::response('', 404),
        };
    }
}
