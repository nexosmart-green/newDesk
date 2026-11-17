<?php

namespace App\Domain\StatusPage\Status;

use App\Domain\Monitoring\Sites\SiteChecker;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * GETs the URL the team configured on an http/health component (monitoreo sitecheck.Checker.Probe):
 * no redirects followed (a 3xx is up), one try, the leaf certificate's expiry and the first 64 KB of
 * the body (a /health JSON). Unlike the site checks, any address is allowed, as in monitoreo: the
 * team typed this URL.
 */
final class HttpProber
{
    private const TIMEOUT = 15;

    private const CONNECT_TIMEOUT = 8;

    private const BODY_LIMIT = 65536;

    private const CONCURRENCY = 10;

    /**
     * @param  array<int|string, string>  $urls  by key
     * @return array<int|string, ProbeResult> by the same key
     */
    public function probe(array $urls): array
    {
        $results = [];
        foreach (array_chunk($urls, self::CONCURRENCY, true) as $chunk) {
            $logs = [];
            $responses = Http::pool(function (Pool $pool) use ($chunk, &$logs) {
                foreach ($chunk as $key => $url) {
                    $logs[$key] = fopen('php://temp', 'w+');
                    $pool->as((string) $key)
                        ->withUserAgent(config('newdesk.monitoring.sitecheck_user_agent') ?: SiteChecker::DEFAULT_USER_AGENT)
                        ->timeout(self::TIMEOUT)
                        ->connectTimeout(self::CONNECT_TIMEOUT)
                        ->withoutRedirecting()
                        ->withOptions(['curl' => [
                            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                            CURLOPT_FORBID_REUSE => true,
                            CURLOPT_CERTINFO => true,
                            CURLOPT_VERBOSE => true,
                            CURLOPT_STDERR => $logs[$key],
                        ]])
                        ->get($url);
                }
            });
            foreach ($chunk as $key => $url) {
                $results[$key] = $this->result($responses[(string) $key], $logs[$key]);
                fclose($logs[$key]);
            }
        }

        return $results;
    }

    /** @param  resource  $log */
    private function result(mixed $response, $log): ProbeResult
    {
        if ($response instanceof Response) {
            $stats = $response->handlerStats();
            // Time to the first byte, as monitoreo's client.Do: the body is not part of the latency.
            $seconds = $stats['starttransfer_time'] ?? $response->transferStats?->getTransferTime() ?? 0;

            // ponytail: the whole body is read before cutting it to 64 KB; stream it if a huge page shows up.
            return new ProbeResult(
                $response->status(),
                (int) round($seconds * 1000),
                SiteChecker::certificateExpiry($stats),
                mb_scrub(substr($response->body(), 0, self::BODY_LIMIT)),
            );
        }
        if (! $response instanceof ConnectionException) {
            throw $response;
        }
        $previous = $response->getPrevious();
        $curl = $previous instanceof GuzzleConnectException || $previous instanceof GuzzleRequestException ? $previous->getHandlerContext() : [];
        $curl['error'] = ($curl['error'] ?? '') ?: $response->getMessage();
        rewind($log);

        return new ProbeResult(error: SiteChecker::failureReason($curl, (string) stream_get_contents($log)));
    }
}
