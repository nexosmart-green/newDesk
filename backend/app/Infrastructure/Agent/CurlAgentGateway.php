<?php

namespace App\Infrastructure\Agent;

use App\Domain\Monitoring\Agent\AgentError;
use App\Domain\Monitoring\Agent\AgentResponse;
use App\Domain\Monitoring\Agent\AgentTarget;
use App\Domain\Monitoring\Agent\Fingerprint;
use App\Domain\Monitoring\Agent\Section;
use App\Domain\Monitoring\Contracts\AgentGateway;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * monitor-agent over HTTPS with curl-multi (ADR-0002 §2). The agent's certificate is self-signed:
 * trust comes from the public-key pin, derived once from the fingerprint monitoreo stores and
 * checked by curl before any byte (the token included) is sent.
 */
final class CurlAgentGateway implements AgentGateway
{
    /** One agent answer at most (monitoreo agentclient maxSectionBytes). */
    private const MAX_BYTES = 8 << 20;

    private const CONNECT_TIMEOUT = 5;

    private ?CurlMultiHandler $multi = null;

    private Client $client;

    /** @var list<array{socket: resource, promise: Promise, deadline: float}> */
    private array $probes = [];

    /** @param  callable|null  $handler  Guzzle handler; tests pass a MockHandler */
    public function __construct(?callable $handler = null)
    {
        if ($handler === null) {
            // A short select keeps the poller's own loop responsive while transfers run.
            $handler = $this->multi = new CurlMultiHandler(['select_timeout' => 0.05]);
        }
        $this->client = new Client(['handler' => HandlerStack::create($handler)]);
    }

    public function pin(string $ip, int $port, string $fingerprint): string
    {
        $fingerprint = Fingerprint::normalize($fingerprint);
        if (! Fingerprint::isValid($fingerprint)) {
            throw AgentError::pinMismatch();
        }
        $context = stream_context_create(['ssl' => [
            // The fingerprint replaces chain and name checks (self-signed agent reached by IP).
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'SNI_enabled' => false,
            'capture_peer_cert' => true,
            // Array form: a bare 64-char string is not read as SHA-256.
            'peer_fingerprint' => ['sha256' => $fingerprint],
        ]]);

        $warning = '';
        set_error_handler(function (int $level, string $message) use (&$warning) {
            $warning = $message;

            return true;
        });
        try {
            $socket = stream_socket_client('tcp://'.self::host($ip).':'.$port, $errno, $error, self::CONNECT_TIMEOUT, STREAM_CLIENT_CONNECT, $context);
            if ($socket === false) {
                throw AgentError::other($error !== '' ? $error : ($warning !== '' ? $warning : 'no se pudo conectar'));
            }
            stream_set_timeout($socket, self::CONNECT_TIMEOUT);
            $handshake = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
            $certificate = stream_context_get_params($socket)['options']['ssl']['peer_certificate'] ?? null;
            fclose($socket); // nothing is written: the pin is all we wanted
        } finally {
            restore_error_handler();
        }

        if ($handshake !== true) {
            throw str_contains($warning, 'peer_fingerprint') ? AgentError::pinMismatch() : AgentError::other($warning ?: 'falló el handshake TLS');
        }
        // Belt and braces: the pin must come from exactly the certificate that was entered.
        if ($certificate === null || openssl_x509_fingerprint($certificate, 'sha256') !== $fingerprint) {
            throw AgentError::pinMismatch();
        }
        $publicKey = openssl_pkey_get_details(openssl_pkey_get_public($certificate))['key'];
        $spki = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $publicKey));

        return base64_encode(hash('sha256', $spki, true));
    }

    public function fetch(AgentTarget $target, string $section): PromiseInterface
    {
        if ($target->pin === '') {
            return Create::rejectionFor(AgentError::other('falta el pin TLS del agente'));
        }

        return $this->client->getAsync('https://'.self::host($target->ip).':'.$target->port.'/v1/'.$section, [
            'http_errors' => false,
            'allow_redirects' => false,
            'verify' => false,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'timeout' => $section === Section::HEALTH ? 10 : 60,
            'headers' => ['Authorization' => 'Bearer '.$target->token, 'User-Agent' => 'monitor-panel'],
            'curl' => [
                CURLOPT_PINNEDPUBLICKEY => 'sha256//'.$target->pin,
                CURLOPT_MAXFILESIZE_LARGE => self::MAX_BYTES,
            ],
        ])->then(
            fn (ResponseInterface $response) => $this->decode($response, $section),
            fn (Throwable $e) => throw $this->transportError($e, $section),
        );
    }

    public function probe(string $ip, int $port, int $timeoutSeconds): PromiseInterface
    {
        $socket = @stream_socket_client('tcp://'.self::host($ip).':'.$port, $errno, $error, $timeoutSeconds,
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);
        if ($socket === false) {
            return Create::promiseFor(false);
        }
        $promise = new Promise(function () use (&$promise) {
            while ($promise->getState() === PromiseInterface::PENDING) {
                $this->tick();
                usleep(10_000);
            }
        });
        $this->probes[] = ['socket' => $socket, 'promise' => $promise, 'deadline' => microtime(true) + $timeoutSeconds];

        return $promise;
    }

    public function tick(): void
    {
        if ($this->multi !== null) {
            $this->multi->tick();
        }
        $this->tickProbes();
        Utils::queue()->run();
    }

    /** An async connect is done when the socket turns writable (or errored, on Windows). */
    private function tickProbes(): void
    {
        $pending = [];
        foreach ($this->probes as $probe) {
            $read = null;
            $write = $except = [$probe['socket']];
            $ready = @stream_select($read, $write, $except, 0);
            if ($ready === 0 && microtime(true) < $probe['deadline']) {
                $pending[] = $probe;

                continue;
            }
            $connected = $ready > 0 && $except === [] && stream_socket_get_name($probe['socket'], true) !== false;
            fclose($probe['socket']);
            $probe['promise']->resolve($connected);
        }
        $this->probes = $pending;
    }

    private function decode(ResponseInterface $response, string $section): AgentResponse
    {
        $body = (string) $response->getBody();
        if (strlen($body) > self::MAX_BYTES) {
            throw self::tooLarge($section);
        }

        $status = $response->getStatusCode();
        if ($status === 200) {
            return $this->parse($body, $section);
        }
        $agentError = json_decode($body)->error ?? null;

        throw match ($status) {
            401 => AgentError::unauthorized(),
            404 => AgentError::unsupported(),
            default => AgentError::other("agent {$status}: ".(is_string($agentError) && $agentError !== '' ? $agentError : $response->getReasonPhrase())),
        };
    }

    private function parse(string $body, string $section): AgentResponse
    {
        try {
            return AgentResponse::fromJson($body);
        } catch (\JsonException $e) {
            throw AgentError::other("decode {$section}: {$e->getMessage()}");
        }
    }

    private function transportError(Throwable $e, string $section): Throwable
    {
        if (! $e instanceof TransferException) {
            return $e;
        }
        $context = method_exists($e, 'getHandlerContext') ? $e->getHandlerContext() : [];

        return match ($context['errno'] ?? 0) {
            CURLE_OPERATION_TIMEDOUT => AgentError::timeout(),
            CURLE_SSL_PINNEDPUBKEYNOTMATCH => AgentError::pinMismatch(),
            CURLE_FILESIZE_EXCEEDED => self::tooLarge($section),
            default => AgentError::other($context['error'] ?? $e->getMessage()),
        };
    }

    private static function tooLarge(string $section): AgentError
    {
        return AgentError::other("la respuesta de {$section} supera ".(self::MAX_BYTES >> 20).' MiB');
    }

    /** IPv6 literals go in brackets in URLs and stream targets. */
    private static function host(string $ip): string
    {
        return str_contains($ip, ':') ? "[{$ip}]" : $ip;
    }
}
