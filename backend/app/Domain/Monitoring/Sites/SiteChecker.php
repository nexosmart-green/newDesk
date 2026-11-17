<?php

namespace App\Domain\Monitoring\Sites;

use Carbon\CarbonImmutable;
use Closure;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * monitoreo sitecheck.NewPublic (RF-13.05): GET https://<name>/ the way a visitor reaches it,
 * without following redirects (a 3xx is up), recording the leaf certificate's expiry.
 *
 * Only public IPs are reached (D-085, CA-13.10): the name is resolved here and curl is pinned to
 * the public addresses with CURLOPT_RESOLVE, so a later DNS answer cannot rebind it inside.
 * Without an HTTP answer a name is tried twice more, 10 s apart, each time on a new connection
 * (D-075, CA-13.09); any status code, even a 5xx, is final.
 */
final class SiteChecker
{
    public const DEFAULT_USER_AGENT = 'monitoreo-nexosmart/1.0 (+uptime check)';

    public const RETRIES = 2;

    public const RETRY_DELAY = 10;

    public const TIMEOUT = 15;

    private const CONNECT_TIMEOUT = 8;

    private const CONCURRENCY = 4;

    private const NOT_PUBLIC = 'la IP no es pública';

    /**
     * Reachable from a monitored server's name but not public: they pass FILTER_FLAG_GLOBAL_RANGE.
     */
    private const RESERVED_PREFIXES = ['224.0.0.0/4', 'ff00::/8', '64:ff9b::/96', '64:ff9b:1::/48', 'fec0::/10'];

    /** @var Closure(string): list<string> */
    private readonly Closure $resolve;

    /** @param  (Closure(string): list<string>)|null  $resolve  a name's addresses (default: the system resolver, /etc/hosts included) */
    public function __construct(?Closure $resolve = null)
    {
        $this->resolve = $resolve ?? self::systemResolve(...);
    }

    /**
     * Checks every name before $deadline. Retries go in rounds: the names without an answer wait
     * RETRY_DELAY together, then are tried again. A check the deadline cut is left out, so its last
     * stored result stays and no site goes down for it (D-106).
     *
     * @param  list<string>  $names
     * @return array<string, array{url: string, status_code: int|null, latency_ms: int|null, ok: bool,
     *     error: string|null, ssl_expires_at: CarbonImmutable|null, checked_at: CarbonImmutable}> by name
     */
    public function checkAll(array $names, CarbonImmutable $deadline): array
    {
        $results = [];
        $pending = $names;
        for ($try = 0; $try <= self::RETRIES && $pending !== []; $try++) {
            if ($try > 0) {
                if (CarbonImmutable::now()->addSeconds(self::RETRY_DELAY)->gte($deadline)) {
                    break; // no time left to retry: the last failure stands
                }
                Sleep::for(self::RETRY_DELAY)->seconds();
            }
            foreach (array_chunk($pending, self::CONCURRENCY) as $chunk) {
                $left = CarbonImmutable::now()->diffInMilliseconds($deadline, false) / 1000;
                if ($left <= 0) {
                    break;
                }
                foreach ($this->probe($chunk, min(self::TIMEOUT, $left)) as $name => $check) {
                    if ($check['cut'] && $left < self::TIMEOUT) {
                        unset($results[$name]);
                    } else {
                        unset($check['cut']);
                        $results[$name] = $check;
                    }
                }
            }
            $pending = array_values(array_filter($pending, fn (string $name) => ($results[$name]['error'] ?? null) !== null));
        }

        return $results;
    }

    /** Public unicast only: no private, loopback, link-local, CGNAT, ULA, multicast or reserved address (D-085). */
    public static function isPublicAddress(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            return false;
        }
        $packed = inet_pton($ip);
        foreach (self::RESERVED_PREFIXES as $prefix) {
            [$network, $bits] = explode('/', $prefix);
            $network = inet_pton($network);
            $bits = (int) $bits;
            if (strlen($network) !== strlen($packed)) {
                continue;
            }
            $mask = str_repeat("\xff", intdiv($bits, 8)).($bits % 8 ? chr((0xFF << (8 - $bits % 8)) & 0xFF) : '');
            if (($packed & $mask) === ($network & $mask)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Why there was no HTTP answer, in monitoreo's words. A timeout says how far the request got,
     * from curl's verbose log: it tells a link that drops the answer from a site that never
     * accepts the connection (D-075).
     *
     * @param  array<string, mixed>  $curl  errno, error and curl_getinfo() of the failed transfer
     */
    public static function failureReason(array $curl, string $verbose): string
    {
        $errno = (int) ($curl['errno'] ?? 0);
        $error = (string) ($curl['error'] ?? '');

        return match (true) {
            $errno === CURLE_SSL_CACERT => 'certificado inválido', // 60, CURLE_PEER_FAILED_VERIFICATION
            $errno === CURLE_COULDNT_RESOLVE_HOST => 'DNS no resuelve',
            $errno === CURLE_OPERATION_TIMEDOUT => match (true) {
                (bool) preg_match('/^> /m', $verbose) => 'timeout esperando respuesta',
                str_contains($verbose, 'SSL connection using') => 'timeout enviando el pedido',
                (bool) preg_match('/Connected to|Established connection|ALPN|TLS/', $verbose) => 'timeout en TLS',
                default => 'timeout al conectar',
            },
            // curl 8 says "Couldn't connect to server"; the refusal is in its verbose log.
            $errno === CURLE_COULDNT_CONNECT && stripos($error.$verbose, 'connection refused') !== false => 'conexión rechazada',
            str_contains($error, 'unrecognized name'), str_contains($error, 'handshake failure') => 'TLS: el edge no tiene certificado para este dominio',
            default => mb_substr($error, 0, 200),
        };
    }

    /**
     * The leaf certificate's expiry from curl's CURLOPT_CERTINFO ("Dec 25 22:56:35 2026 GMT").
     *
     * @param  array<string, mixed>  $curlInfo  curl_getinfo() of the transfer
     */
    public static function certificateExpiry(array $curlInfo): ?CarbonImmutable
    {
        $expires = $curlInfo['certinfo'][0]['Expire date'] ?? null;

        return is_string($expires) ? CarbonImmutable::parse($expires)->utc() : null;
    }

    /**
     * One GET per name, all at once.
     *
     * @param  list<string>  $names
     * @return array<string, array<string, mixed>> by name, with `cut`: timed out (maybe by the deadline)
     */
    private function probe(array $names, float $timeout): array
    {
        $checks = [];
        $addresses = [];
        foreach ($names as $name) {
            $resolved = ($this->resolve)($name);
            $public = array_values(array_filter($resolved, self::isPublicAddress(...)));
            if ($public === []) {
                $checks[$name] = $this->failed($name, $resolved === [] ? 'DNS no resuelve' : self::NOT_PUBLIC);
            } else {
                $addresses[$name] = $public;
            }
        }

        $logs = [];
        $responses = $addresses === [] ? [] : Http::pool(function (Pool $pool) use ($addresses, $timeout, &$logs) {
            foreach ($addresses as $name => $ips) {
                $logs[$name] = fopen('php://temp', 'w+');
                $pool->as($name)
                    ->withUserAgent(config('newdesk.monitoring.sitecheck_user_agent') ?: self::DEFAULT_USER_AGENT)
                    ->timeout($timeout)
                    ->connectTimeout(min(self::CONNECT_TIMEOUT, $timeout))
                    ->withoutRedirecting()
                    ->withOptions(['curl' => [
                        CURLOPT_RESOLVE => [$name.':443:'.implode(',', array_map(
                            fn (string $ip) => str_contains($ip, ':') ? "[{$ip}]" : $ip, $ips,
                        ))],
                        CURLOPT_NOPROXY => '*',
                        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                        CURLOPT_FRESH_CONNECT => true,
                        CURLOPT_FORBID_REUSE => true,
                        CURLOPT_CERTINFO => true,
                        CURLOPT_VERBOSE => true,
                        CURLOPT_STDERR => $logs[$name],
                    ]])
                    ->get("https://{$name}/");
            }
        });

        foreach ($responses as $name => $response) {
            $checks[$name] = $this->result($name, $response, $logs[$name]);
            fclose($logs[$name]);
        }

        return $checks;
    }

    /** @param  resource  $log */
    private function result(string $name, Response|Throwable $response, $log): array
    {
        if ($response instanceof Response) {
            $status = $response->status();

            return [
                'url' => "https://{$name}/",
                'status_code' => $status,
                'latency_ms' => (int) round(($response->transferStats?->getTransferTime() ?? 0) * 1000),
                'ok' => $status >= 200 && $status < 400,
                'error' => null,
                'ssl_expires_at' => self::certificateExpiry($response->handlerStats()),
                'checked_at' => CarbonImmutable::now()->utc(),
                'cut' => false,
            ];
        }
        if (! $response instanceof ConnectionException) {
            throw $response;
        }
        $previous = $response->getPrevious();
        $curl = $previous instanceof GuzzleConnectException || $previous instanceof GuzzleRequestException ? $previous->getHandlerContext() : [];
        $curl['error'] = ($curl['error'] ?? '') ?: $response->getMessage();
        rewind($log);

        return [
            ...$this->failed($name, self::failureReason($curl, (string) stream_get_contents($log))),
            'cut' => ($curl['errno'] ?? null) === CURLE_OPERATION_TIMEDOUT,
        ];
    }

    private function failed(string $name, string $reason): array
    {
        return [
            'url' => "https://{$name}/",
            'status_code' => null,
            'latency_ms' => null,
            'ok' => false,
            'error' => $reason,
            'ssl_expires_at' => null,
            'checked_at' => CarbonImmutable::now()->utc(),
            'cut' => false,
        ];
    }

    /** @return list<string> IPv4 from the system resolver; IPv6 only when the name has no IPv4. */
    private static function systemResolve(string $name): array
    {
        $ips = gethostbynamel($name) ?: [];
        if ($ips === []) {
            // ponytail: dns_get_record asks DNS directly (no /etc/hosts); fine for the rare IPv6-only site.
            $ips = array_column(@dns_get_record($name, DNS_AAAA) ?: [], 'ipv6');
        }

        return $ips;
    }
}
