<?php

namespace App\Infrastructure\Discord;

use App\Domain\Assistant\Contracts\DiscordInteractionClient;
use App\Domain\Assistant\Discord\DiscordReply;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Discord REST for interactions: the follow-up webhook (authorized by the interaction token itself, which is in
 * the URL — so no error from here ever carries the URL into a log), and with monitoreo's bot token the answers to
 * DMs and the guild commands.
 */
final class DiscordInteractionHttpClient implements DiscordInteractionClient
{
    public function __construct(private readonly Config $config) {}

    public function editOriginal(string $interactionToken, string $content, array $components, array $files = []): void
    {
        $url = DiscordBotGateway::BASE_URL.'/webhooks/'.$this->applicationId().'/'.$interactionToken.'/messages/@original';
        $this->send($this->request(), 'patch', $url, DiscordReply::body($content, $components), $files);
    }

    public function createMessage(string $channelId, string $content, array $components = [], array $files = []): void
    {
        $url = DiscordBotGateway::BASE_URL."/channels/{$channelId}/messages";
        $this->send($this->request()->withToken($this->botToken(), 'Bot'), 'post', $url, DiscordReply::body($content, $components, ephemeral: false), $files);
    }

    public function typing(string $channelId): void
    {
        $response = $this->safely(fn () => Http::timeout(5)->withToken($this->botToken(), 'Bot')->post(DiscordBotGateway::BASE_URL."/channels/{$channelId}/typing"));
        if ($response->failed()) {
            throw new RuntimeException("Discord no aceptó el aviso de escritura ({$response->status()}).");
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  list<array{name: string, contents: string}>  $files
     */
    private function send(PendingRequest $request, string $method, string $url, array $body, array $files): void
    {
        $response = $this->safely(function () use ($request, $method, $url, $body, $files) {
            if ($files === []) {
                return $request->{$method}($url, $body);
            }
            $body['attachments'] = array_map(fn (array $file, int $i) => ['id' => $i, 'filename' => $file['name']], $files, array_keys($files));
            $request = $request->asMultipart()->attach('payload_json', json_encode($body, JSON_THROW_ON_ERROR));
            foreach ($files as $i => $file) {
                $request = $request->attach("files[{$i}]", $file['contents'], $file['name']);
            }

            return $request->{$method}($url);
        });

        if ($response->failed()) {
            throw new RuntimeException("Discord no aceptó la respuesta ({$response->status()}).");
        }
    }

    public function registerCommands(array $commands): void
    {
        $guild = (string) $this->config->get('newdesk.monitoring.discord.guild_id');
        $response = Http::withToken($this->botToken(), 'Bot')
            ->put(DiscordBotGateway::BASE_URL.'/applications/'.$this->applicationId()."/guilds/{$guild}/commands", $commands);

        if ($response->failed()) {
            throw new RuntimeException("Discord no aceptó los comandos ({$response->status()}): ".$response->body());
        }
    }

    /** Retried only when Discord is busy or failing (429, 5xx), never on an expired token (401/404). */
    private function request(): PendingRequest
    {
        return Http::timeout(15)->retry(2, 1000, fn (Throwable $e) => $e instanceof RequestException
            && ($e->response->status() === 429 || $e->response->serverError()), throw: false);
    }

    /** @param  callable(): Response  $send */
    private function safely(callable $send): Response
    {
        try {
            return $send();
        } catch (Throwable $e) {
            // Connection errors quote the URL, and the URL holds the interaction token.
            throw new RuntimeException('Discord no respondió ('.class_basename($e).').');
        }
    }

    private function botToken(): string
    {
        return (string) $this->config->get('newdesk.monitoring.discord.bot_token');
    }

    private function applicationId(): string
    {
        return (string) $this->config->get('newdesk.assistant.discord.application_id');
    }
}
