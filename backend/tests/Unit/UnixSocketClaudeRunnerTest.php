<?php

use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Infrastructure\Claude\UnixSocketClaudeRunner as Runner;

/** Port of monitoreo's dailyreport_test.go TestClaudeRun and TestStatus (D-074). */
function claudeFailure(callable $call): BusinessRuleViolation
{
    try {
        $call();
    } catch (BusinessRuleViolation $e) {
        return $e;
    }
    throw new RuntimeException('Claude did not fail');
}

/**
 * A stand-in for monitor-claude.socket in another process: like claude -p, it reads its stdin up to EOF
 * (our side closing) and then answers. Returns [socket path, file with the prompt it got, process].
 *
 * @return array{0: string, 1: string, 2: resource}
 */
function fakeClaudeSocket(string $answer, int $delayMs = 0): array
{
    $dir = sys_get_temp_dir().'/claude-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $script = <<<'PHP'
        [, $socket, $promptFile, $answer, $delay] = $argv;
        $server = stream_socket_server("unix://{$socket}");
        fwrite(STDOUT, "ready\n");
        $connection = stream_socket_accept($server, 10);
        file_put_contents($promptFile, stream_get_contents($connection));
        usleep((int) $delay * 1000);
        fwrite($connection, $answer);
        fclose($connection);
        PHP;
    $process = proc_open([PHP_BINARY, '-r', $script, '--', "{$dir}/c.sock", "{$dir}/prompt", $answer, (string) $delayMs], [1 => ['pipe', 'w']], $pipes);
    fgets($pipes[1]);

    return ["{$dir}/c.sock", "{$dir}/prompt", $process];
}

it('reads the JSON answer of claude -p and says why there is none', function () {
    expect(Runner::interpret('{"type":"result","is_error":false,"result":"Acme:\n- Se agregó el carrito.\n"}'))->toBe("Acme:\n- Se agregó el carrito.");

    $loggedOut = claudeFailure(fn () => Runner::interpret('{"type":"result","is_error":true,"result":"Not logged in · Please run /login"}'));
    expect($loggedOut->status)->toBe(409)->and($loggedOut->getMessage())->toBe('Claude de root del server 5 se deslogueó: entrá al server como root y hacé /login.')
        ->and(claudeFailure(fn () => Runner::interpret('Invalid API key · Please run /login'))->status)->toBe(409);

    $failed = claudeFailure(fn () => Runner::interpret('{"type":"result","is_error":true,"result":"rate limited"}'));
    expect($failed->status)->toBe(502)->and($failed->getMessage())->toBe('Claude falló: rate limited');
    // claude died before printing anything (wrong ExecStart, crash): its stderr is in the journal.
    expect(claudeFailure(fn () => Runner::interpret(''))->getMessage())->toContain('journalctl')
        ->and(claudeFailure(fn () => Runner::interpret('Segmentation fault'))->getMessage())->toBe('Claude devolvió algo ilegible: Segmentation fault');
});

it('is unavailable without the socket', function () {
    $missing = new Runner(sys_get_temp_dir().'/nope-'.bin2hex(random_bytes(4)).'.sock', 1);
    expect($missing->available())->toBeFalse()
        ->and(claudeFailure(fn () => $missing->run('x'))->getMessage())->toStartWith('No se pudo hablar con Claude de root')
        ->and(config('newdesk.monitoring.claude_socket'))->toBe('/run/monitor-claude.sock');
});

it('writes the prompt as claude\'s stdin, closes its side and reads the answer within its time', function () {
    [$socket, $promptFile, $process] = fakeClaudeSocket('{"type":"result","is_error":false,"result":"Acme:\n- Se agregó el carrito.\n"}');
    $runner = new Runner($socket, 30);
    expect($runner->available())->toBeTrue()
        ->and($runner->run('commits de hoy'))->toBe("Acme:\n- Se agregó el carrito.")
        ->and(file_get_contents($promptFile))->toBe('commits de hoy');
    proc_close($process);

    [$socket, , $slow] = fakeClaudeSocket('{}', delayMs: 3000);
    expect(claudeFailure(fn () => (new Runner($socket, 0.3))->run('x'))->getMessage())->toBe('Claude no terminó en 0.3 s');
    proc_terminate($slow);
})->skip(! in_array('unix', stream_get_transports(), true), 'Este PHP no tiene sockets unix (Windows).');
