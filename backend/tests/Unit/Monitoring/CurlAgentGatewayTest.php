<?php

use App\Domain\Monitoring\Agent\AgentError;
use App\Domain\Monitoring\Agent\AgentResponse;
use App\Domain\Monitoring\Agent\AgentTarget;
use App\Infrastructure\Agent\CurlAgentGateway;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

function agentTarget(string $pin = 'cGlu'): AgentTarget
{
    return new AgentTarget('203.0.113.10', 9100, 'the-agent-token-0123', $pin);
}

/** The AgentError a canned transport outcome turns into. */
function agentErrorFor(Response|Throwable $outcome, string $section = 'metrics'): AgentError
{
    $gateway = new CurlAgentGateway(new MockHandler([$outcome]));
    try {
        $gateway->fetch(agentTarget(), $section)->wait();
    } catch (AgentError $e) {
        return $e;
    }
    throw new LogicException('the call did not fail');
}

function curlFailure(int $errno, string $error): ConnectException
{
    return new ConnectException($error, new Request('GET', 'https://203.0.113.10:9100/v1/metrics'), null, ['errno' => $errno, 'error' => $error]);
}

it('sends the bearer token and the pin, and keeps the agent JSON as sent', function () {
    $mock = new MockHandler([new Response(200, [], '{"collected_at":"2026-11-06T12:34:56.123456789Z","agent_version":"v1.2.3","data":{"a":{},"b":[],"c":1.5},"warnings":["pm2 missing"]}')]);
    $response = (new CurlAgentGateway($mock))->fetch(agentTarget(), 'metrics')->wait();
    $request = $mock->getLastRequest();
    $options = $mock->getLastOptions();

    expect($response)->toBeInstanceOf(AgentResponse::class)
        ->and($response->collectedAt->toIso8601ZuluString())->toBe('2026-11-06T12:34:56Z')
        ->and($response->agentVersion)->toBe('v1.2.3')
        ->and($response->dataJson())->toBe('{"a":{},"b":[],"c":1.5}')
        ->and($response->warnings)->toBe(['pm2 missing'])
        ->and((string) $request->getUri())->toBe('https://203.0.113.10:9100/v1/metrics')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer the-agent-token-0123')
        ->and($request->getHeaderLine('User-Agent'))->toBe('monitor-panel')
        ->and($options['curl'][CURLOPT_PINNEDPUBLICKEY])->toBe('sha256//cGlu')
        ->and($options['verify'])->toBeFalse()
        ->and($options['allow_redirects'])->toBeFalse()
        ->and($options['timeout'])->toBe(60);
});

it('never sends a request without a pin (CA-13.02)', function () {
    $mock = new MockHandler([new Response(200, [], '{}')]);

    expect(fn () => (new CurlAgentGateway($mock))->fetch(agentTarget(pin: ''), 'health')->wait())->toThrow(AgentError::class);
    expect($mock->count())->toBe(1); // the canned answer was never asked for
});

it('maps agent answers to monitoreo error texts', function () {
    expect(agentErrorFor(new Response(401, [], '{"error":"unauthorized"}'))->getMessage())->toBe('token inválido')
        ->and(agentErrorFor(new Response(404, [], '{"error":"not found"}')))
        ->kind->toBe(AgentError::UNSUPPORTED)
        ->getMessage()->toBe('el agente es anterior a esta función: hay que actualizarlo')
        ->and(agentErrorFor(new Response(503, [], '{"error":"collector failed"}'))->getMessage())->toBe('agent 503: collector failed')
        ->and(agentErrorFor(new Response(502, [], 'bad gateway'))->getMessage())->toBe('agent 502: Bad Gateway')
        ->and(agentErrorFor(new Response(200, [], 'not json'))->getMessage())->toStartWith('decode metrics:');
});

it('maps transport failures: timeout, pin mismatch, oversized answer', function () {
    expect(agentErrorFor(curlFailure(CURLE_OPERATION_TIMEDOUT, 'Operation timed out'))->getMessage())->toBe('timeout')
        ->and(agentErrorFor(curlFailure(CURLE_SSL_PINNEDPUBKEYNOTMATCH, 'SSL: public key does not match pinned public key')))
        ->kind->toBe(AgentError::PIN_MISMATCH)
        ->getMessage()->toBe('la huella no coincide')
        ->and(agentErrorFor(curlFailure(CURLE_FILESIZE_EXCEEDED, 'Maximum file size exceeded'))->getMessage())->toBe('la respuesta de metrics supera 8 MiB')
        ->and(agentErrorFor(curlFailure(7, 'Failed to connect to 203.0.113.10 port 9100'))->getMessage())->toBe('Failed to connect to 203.0.113.10 port 9100');
});

it('rejects an answer above 8 MiB even without curl noticing', function () {
    $huge = '{"data":"'.str_repeat('x', 9 << 20).'"}';

    expect(agentErrorFor(new Response(200, [], $huge))->getMessage())->toBe('la respuesta de metrics supera 8 MiB');
});
