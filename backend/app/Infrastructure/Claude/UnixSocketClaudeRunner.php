<?php

namespace App\Infrastructure\Claude;

use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Shared\Text;
use App\Domain\Support\Contracts\ClaudeRunner;

/**
 * monitoreo dailyreport.Claude (D-074): systemd listens on a unix socket and starts root's `claude -p`
 * per connection, with its stdin and stdout on it. We write the prompt, close our side (claude's EOF)
 * and read its JSON answer. newDesk never runs claude itself.
 */
final class UnixSocketClaudeRunner implements ClaudeRunner
{
    /** Claude has 90 s (D-074). */
    public const TIMEOUT = 90.0;

    private const MAX_ANSWER = 1 << 20;

    private readonly string $socket;

    public function __construct(?string $socket = null, private readonly float $timeout = self::TIMEOUT)
    {
        $this->socket = $socket ?? (string) config('newdesk.monitoring.claude_socket');
    }

    public function available(): bool
    {
        return $this->socket !== '' && file_exists($this->socket);
    }

    public function run(string $prompt): string
    {
        $deadline = microtime(true) + $this->timeout;
        $connection = @stream_socket_client('unix://'.$this->socket, $errno, $error, $this->timeout);
        if ($connection === false) {
            throw self::failed("No se pudo hablar con Claude de root: {$error}");
        }
        try {
            return self::interpret($this->exchange($connection, $prompt, $deadline));
        } finally {
            fclose($connection);
        }
    }

    /**
     * What claude printed, judged as monitoreo does: its JSON result, or why there is none.
     *
     * @throws BusinessRuleViolation
     */
    public static function interpret(string $answer): string
    {
        $trimmed = trim($answer);
        if ($trimmed === '') {
            throw self::failed("Claude de root no respondió: mirá journalctl -u 'monitor-claude@*' en el server 5");
        }
        $out = json_decode($trimmed, true);
        if (! is_array($out)) {
            throw self::notLoggedIn($trimmed) ? self::loggedOut() : self::failed('Claude devolvió algo ilegible: '.Text::clip($trimmed, 300));
        }
        $result = trim((string) ($out['result'] ?? ''));
        if (($out['is_error'] ?? false) === true) {
            throw self::notLoggedIn($result) ? self::loggedOut() : self::failed('Claude falló: '.Text::clip($result, 300));
        }

        return $result;
    }

    /** @param  resource  $connection */
    private function exchange($connection, string $prompt, float $deadline): string
    {
        for ($written = 0; $written < strlen($prompt); $written += $sent) {
            $this->arm($connection, $deadline);
            $sent = fwrite($connection, substr($prompt, $written));
            if ($sent === false || $sent === 0) {
                throw $this->timedOut($connection) ?? self::failed('Se cortó la conexión con Claude de root al mandarle el pedido');
            }
        }
        stream_socket_shutdown($connection, STREAM_SHUT_WR);

        $answer = '';
        while (! feof($connection) && strlen($answer) < self::MAX_ANSWER) {
            $this->arm($connection, $deadline);
            $chunk = fread($connection, min(65536, self::MAX_ANSWER - strlen($answer)));
            if ($timeout = $this->timedOut($connection)) {
                throw $timeout;
            }
            if ($chunk === false) {
                throw self::failed('Se cortó la conexión con Claude de root');
            }
            $answer .= $chunk;
        }

        return $answer;
    }

    /** Each read and write gets what is left of the 90 s. @param  resource  $connection */
    private function arm($connection, float $deadline): void
    {
        $left = $deadline - microtime(true);
        if ($left <= 0) {
            throw $this->timeout();
        }
        stream_set_timeout($connection, (int) $left, (int) (($left - floor($left)) * 1_000_000));
    }

    /** @param  resource  $connection */
    private function timedOut($connection): ?BusinessRuleViolation
    {
        return stream_get_meta_data($connection)['timed_out'] ? $this->timeout() : null;
    }

    private function timeout(): BusinessRuleViolation
    {
        return self::failed(sprintf('Claude no terminó en %s s', rtrim(rtrim(number_format($this->timeout, 1, '.', ''), '0'), '.')));
    }

    private static function notLoggedIn(string $text): bool
    {
        $text = mb_strtolower($text);

        return str_contains($text, 'not logged in') || str_contains($text, '/login') || str_contains($text, 'invalid api key') || str_contains($text, 'oauth token');
    }

    private static function loggedOut(): BusinessRuleViolation
    {
        return new BusinessRuleViolation('Claude de root del server 5 se deslogueó: entrá al server como root y hacé /login.', 'claude_logged_out', 409);
    }

    private static function failed(string $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'claude_failed', 502);
    }
}
