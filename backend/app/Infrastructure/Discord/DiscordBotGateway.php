<?php

namespace App\Infrastructure\Discord;

use App\Domain\Monitoring\Contracts\DiscordGateway;
use App\Domain\Monitoring\Discord\DiscordError;
use App\Domain\Monitoring\Discord\DiscordMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The "Monitoreo" bot through Discord's REST API (monitoreo discord.Client, D-059, D-060): no
 * gateway connection. Discord sends every channel of the server to any bot; what is outside
 * DISCORD_CHANNEL_CATEGORIES is dropped right away: never listed, cached or accepted as a target.
 * A 429 is the caller's to retry (the `discord` queue releases the message).
 */
final class DiscordBotGateway implements DiscordGateway
{
    public const BASE_URL = 'https://discord.com/api/v10';

    /** The panel's pickers do not ask Discord on every page load. */
    private const LIST_TTL = 60;

    private const CHANNELS_KEY = 'monitoring:discord:channels';

    private const MEMBERS_KEY = 'monitoring:discord:members';

    private const ADMINISTRATOR = 1 << 3;

    private const VIEW_CHANNEL = 1 << 10;

    private const SEND_MESSAGES = 1 << 11;

    private const SEND_IN_THREADS = 1 << 38;

    private const CATEGORY = 4;

    private const CHANNEL_TYPES = [0, 5]; // text, announcement

    private const THREAD_TYPES = [10, 11, 12]; // announcement, public and private threads

    /** @param  list<string>  $categories  the only channel groups the panel works with */
    public function __construct(
        private readonly string $token,
        private readonly string $guildId,
        private readonly string $categoryId,
        private readonly array $categories,
    ) {}

    public function configured(): bool
    {
        return true;
    }

    public function send(DiscordMessage $message): void
    {
        if (! DiscordMessage::validId($message->channelId)) {
            throw new DiscordError('id de canal inválido');
        }
        $this->call('post', "/channels/{$message->channelId}/messages", [
            'content' => $message->clippedContent(),
            'allowed_mentions' => ['parse' => [], ...($message->mention === [] ? [] : ['users' => $message->mention])],
        ]);
    }

    public function createChannel(string $name, string $topic): array
    {
        $created = $this->call('post', "/guilds/{$this->guildId}/channels", [
            'name' => $name, 'type' => 0, 'topic' => $topic, ...($this->categoryId === '' ? [] : ['parent_id' => $this->categoryId]),
        ]);
        Cache::forget(self::CHANNELS_KEY);

        return ['id' => (string) ($created['id'] ?? ''), 'name' => (string) ($created['name'] ?? $name)];
    }

    public function allowed(string $channelId): bool
    {
        try {
            return DiscordMessage::validId($channelId) && in_array($channelId, array_column($this->channels(), 'id'), true);
        } catch (DiscordError $e) {
            Log::warning('discord: check channel', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function channels(): array
    {
        return Cache::remember(self::CHANNELS_KEY, self::LIST_TTL, function () {
            $raw = $this->call('get', "/guilds/{$this->guildId}/channels");
            $threads = $this->call('get', "/guilds/{$this->guildId}/threads/active")['threads'] ?? [];
            $roles = $this->call('get', "/guilds/{$this->guildId}/roles");
            $botId = (string) ($this->call('get', '/users/@me')['id'] ?? '');
            $botRoles = $this->call('get', "/guilds/{$this->guildId}/members/{$botId}")['roles'] ?? [];

            $byId = array_column($raw, null, 'id');
            $permissions = $this->permissions($roles, $botRoles, $botId);
            $category = function (array $channel) use ($byId): ?string {
                $parent = $byId[$channel['parent_id'] ?? ''] ?? null;

                return ($parent['type'] ?? null) === self::CATEGORY ? (string) $parent['name'] : null;
            };
            $allowed = fn (array $channel) => in_array($channel['parent_id'] ?? null, $this->categories, true) && $category($channel) !== null;
            $can = fn (int $permissions, int $send) => ($permissions & self::VIEW_CHANNEL) !== 0 && ($permissions & $send) !== 0;

            $out = [];
            foreach ($raw as $channel) {
                if (in_array($channel['type'] ?? null, self::CHANNEL_TYPES, true) && $allowed($channel)) {
                    $out[] = ['id' => (string) $channel['id'], 'name' => (string) $channel['name'], 'kind' => 'channel',
                        'category' => $category($channel), 'can_send' => $can($permissions($channel), self::SEND_MESSAGES)];
                }
            }
            foreach ($threads as $thread) {
                $parent = $byId[$thread['parent_id'] ?? ''] ?? null;
                if (in_array($thread['type'] ?? null, self::THREAD_TYPES, true) && $parent !== null && $allowed($parent)) {
                    $out[] = ['id' => (string) $thread['id'], 'name' => (string) $thread['name'], 'kind' => 'thread',
                        'category' => $category($parent), 'can_send' => $can($permissions($parent), self::SEND_IN_THREADS)];
                }
            }
            usort($out, fn (array $a, array $b) => strcmp((string) $a['category'], (string) $b['category']) ?: strcmp($a['name'], $b['name']));

            return $out;
        });
    }

    public function members(): array
    {
        return Cache::remember(self::MEMBERS_KEY, self::LIST_TTL, function () {
            $out = [];
            $after = '0';
            for ($page = 0; $page < 20; $page++) { // 1000 per page, 20 000 people at most
                $batch = $this->call('get', "/guilds/{$this->guildId}/members", ['limit' => 1000, 'after' => $after]);
                foreach ($batch as $member) {
                    $user = $member['user'] ?? [];
                    $after = (string) ($user['id'] ?? $after);
                    if ($user['bot'] ?? false) {
                        continue;
                    }
                    $out[] = ['id' => (string) $user['id'], 'username' => (string) $user['username'],
                        'display_name' => (string) (($member['nick'] ?? '') ?: (($user['global_name'] ?? '') ?: $user['username']))];
                }
                if (count($batch) < 1000) {
                    break;
                }
            }
            usort($out, fn (array $a, array $b) => strcmp($a['display_name'], $b['display_name']));

            return $out;
        });
    }

    /**
     * Where the bot can post, as Discord computes it: @everyone, the bot's roles, then each channel's
     * overwrites for @everyone, the bot's roles and the bot itself.
     *
     * @param  list<array<string, mixed>>  $roles
     * @param  list<string>  $botRoles
     * @return callable(array<string, mixed>): int
     */
    private function permissions(array $roles, array $botRoles, string $botId): callable
    {
        $rolePermissions = array_map('intval', array_column($roles, 'permissions', 'id'));
        $base = $rolePermissions[$this->guildId] ?? 0; // @everyone's id is the server's
        foreach ($botRoles as $role) {
            $base |= $rolePermissions[$role] ?? 0;
        }

        return function (array $channel) use ($base, $botRoles, $botId): int {
            if (($base & self::ADMINISTRATOR) !== 0) {
                return -1; // every bit
            }
            $overwrites = array_column($channel['permission_overwrites'] ?? [], null, 'id');
            $apply = fn (int $permissions, array $overwrite) => ($permissions & ~(int) $overwrite['deny']) | (int) $overwrite['allow'];
            $permissions = isset($overwrites[$this->guildId]) ? $apply($base, $overwrites[$this->guildId]) : $base;
            $roles = ['allow' => 0, 'deny' => 0];
            foreach ($botRoles as $role) {
                $roles['allow'] |= (int) ($overwrites[$role]['allow'] ?? 0);
                $roles['deny'] |= (int) ($overwrites[$role]['deny'] ?? 0);
            }
            $permissions = $apply($permissions, $roles);

            return isset($overwrites[$botId]) ? $apply($permissions, $overwrites[$botId]) : $permissions;
        };
    }

    /**
     * @param  array<string, mixed>  $data  the JSON body, or the query of a GET
     * @return array<mixed>
     */
    private function call(string $method, string $path, array $data = []): array
    {
        $request = Http::withToken($this->token, 'Bot')->withUserAgent('DiscordBot (https://monitoreo.nexosmart.com, 1.0)')
            ->acceptJson()->timeout(15)->withoutRedirecting();
        try {
            $response = $method === 'get' ? $request->get(self::BASE_URL.$path, $data) : $request->post(self::BASE_URL.$path, $data);
        } catch (ConnectionException $e) {
            throw new DiscordError('sin respuesta de Discord: '.$e->getMessage());
        }
        if ($response->status() >= 300) {
            throw DiscordError::status($response->status(), $response->status() === 429 ? self::retryAfter($response) : 0);
        }

        return (array) ($response->json() ?? []);
    }

    /** Seconds Discord asks to wait: the body's `retry_after`, else the header, else 1. */
    private static function retryAfter(Response $response): int
    {
        $seconds = (float) ($response->json('retry_after') ?? 0) ?: (float) $response->header('Retry-After') ?: 1.0;

        return (int) ceil($seconds);
    }
}
