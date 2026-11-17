<?php

use App\Domain\Monitoring\Sites\SiteChecker;
use App\Domain\Monitoring\Sites\SiteTargets;
use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Port of monitoreo sitecheck_test.go and public_test.go, without network: names resolve through
 * a fake resolver, answers come from Http::fake and the retry waits from Sleep::fake.
 */
beforeEach(function () {
    $this->freezeTime();
    Sleep::fake(syncWithCarbon: true);
    $this->checker = new SiteChecker(fn (string $name) => ['104.16.0.1']);
    $this->deadline = fn () => CarbonImmutable::now()->addMinutes(2);
});

/** A curl failure as Guzzle reports it: errno 28 is a timeout. */
function siteCurlFailure(int $errno, string $error = 'failed'): Closure
{
    return fn (Request $request) => throw new ConnectException($error, $request->toPsrRequest(), null, ['errno' => $errno, 'error' => $error]);
}

it('takes the primary public names of the sites section', function () {
    $names = SiteTargets::fromSites(['items' => [
        ['server_names' => ['www.reaquila.com', 'reaquila.com'], 'redirects' => ['reaquila.com']],
        ['server_names' => ['data.reaquila.com']],
        ['server_names' => ['_', 'localhost', '*.example.com', '51.222.205.245', 'WWW.Reaquila.com']],
    ]]);

    expect($names)->toBe(['data.reaquila.com', 'www.reaquila.com']);
});

it('rejects names a URL parser would read as another host, a port or credentials (D-085)', function () {
    $names = SiteTargets::siteNames(['server_names' => [
        'a@127.0.0.1', '127.0.0.1:3306', 'example.com:8080', 'user@example.com', 'example.com?x=1', 'example.com#frag',
        'exa%6dple.com', 'example.com\\@evil.test', 'www.example.com', 'status.example.com',
    ]]);

    expect($names)->toBe(['www.example.com', 'status.example.com']);
});

it('only calls public unicast addresses public (D-085, CA-13.10)', function () {
    $vector = [
        '10.0.0.1' => false, '172.16.0.1' => false, '192.168.1.1' => false, '100.64.0.1' => false, '169.254.169.254' => false,
        '127.0.0.1' => false, '0.0.0.0' => false, '0.1.2.3' => false, '224.0.0.1' => false, '255.255.255.255' => false, '240.0.0.1' => false,
        '::1' => false, '::' => false, 'fe80::1' => false, 'fc00::1' => false, 'fd12::1' => false, '::ffff:127.0.0.1' => false,
        '64:ff9b::a00:1' => false, '198.18.0.1' => false, 'ff02::1' => false, 'fec0::1' => false,
        '1.1.1.1' => true, '104.16.0.1' => true, '2606:4700::1111' => true,
    ];
    foreach ($vector as $ip => $public) {
        expect(SiteChecker::isPublicAddress($ip))->toBe($public, $ip);
    }
});

it('never connects to a name that resolves inside, nor to one that does not resolve', function () {
    Http::fake();
    $checker = new SiteChecker(fn (string $name) => match ($name) {
        'inside.test' => ['127.0.0.1', '10.0.0.5'],
        default => [],
    });

    $checks = $checker->checkAll(['inside.test', 'nowhere.test'], ($this->deadline)());

    expect($checks['inside.test']['error'])->toBe('la IP no es pública')
        ->and($checks['inside.test']['status_code'])->toBeNull()
        ->and($checks['nowhere.test']['error'])->toBe('DNS no resuelve');
    Http::assertNothingSent();
});

it('counts any answer below 400 as up and does not follow redirects', function () {
    Http::fake([
        'https://ok.test/' => Http::response('', 200),
        'https://down.test/' => Http::response('', 502),
        'https://moved.test/' => Http::response('', 301, ['Location' => 'https://elsewhere.test/']),
        'https://api.test/' => Http::response('', 403),
    ]);

    $checks = $this->checker->checkAll(['ok.test', 'down.test', 'moved.test', 'api.test'], ($this->deadline)());

    expect($checks['ok.test'])->toMatchArray(['url' => 'https://ok.test/', 'status_code' => 200, 'ok' => true, 'error' => null])
        ->and($checks['down.test'])->toMatchArray(['status_code' => 502, 'ok' => false])
        ->and($checks['moved.test'])->toMatchArray(['status_code' => 301, 'ok' => true])
        ->and($checks['api.test'])->toMatchArray(['status_code' => 403, 'ok' => false])
        ->and($checks['ok.test']['checked_at']->toDateTimeString())->toBe(now()->toDateTimeString());
    Http::assertSentCount(4);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'elsewhere'));
});

it('reads the leaf certificate expiry curl reports', function () {
    $expiry = SiteChecker::certificateExpiry(['certinfo' => [
        ['Subject' => 'CN=example.com', 'Expire date' => 'Dec 25 22:56:35 2026 GMT'],
        ['Subject' => 'CN=Some CA', 'Expire date' => 'Jan  1 00:00:00 2030 GMT'],
    ]]);

    expect($expiry->toIso8601ZuluString())->toBe('2026-12-25T22:56:35Z')
        ->and(SiteChecker::certificateExpiry([]))->toBeNull(); // plain HTTP, or a fake
});

it('retries twice 10 s apart without an answer, never after an answer (D-075, CA-13.09)', function () {
    $tries = ['flaky.test' => 0, 'dead.test' => 0, 'broken.test' => 0];
    Http::fake(function (Request $request) use (&$tries) {
        $name = parse_url($request->url(), PHP_URL_HOST);
        $tries[$name]++;

        return match (true) {
            $name === 'broken.test' => Http::response('', 502),
            $name === 'flaky.test' && $tries[$name] > 1 => Http::response('', 200),
            default => siteCurlFailure(CURLE_OPERATION_TIMEDOUT)($request),
        };
    });

    $checks = $this->checker->checkAll(['flaky.test', 'dead.test', 'broken.test'], ($this->deadline)());

    expect($tries)->toBe(['flaky.test' => 2, 'dead.test' => 3, 'broken.test' => 1])
        ->and($checks['flaky.test']['ok'])->toBeTrue()
        ->and($checks['dead.test'])->toMatchArray(['ok' => false, 'status_code' => null, 'error' => 'timeout al conectar'])
        ->and($checks['broken.test'])->toMatchArray(['ok' => false, 'status_code' => 502]);
    Sleep::assertSequence([Sleep::for(10)->seconds(), Sleep::for(10)->seconds()]);
});

it('keeps the last result of a check the round deadline cut, and does not retry past it (D-106)', function () {
    $refused = "Failed to connect to refused.test port 443 after 2 ms: Couldn't connect to server";
    $tries = 0;
    Http::fake(function (Request $request) use (&$tries, $refused) {
        $tries++;

        return str_contains($request->url(), 'refused.test')
            ? siteCurlFailure(CURLE_COULDNT_CONNECT, $refused)($request)
            : siteCurlFailure(CURLE_OPERATION_TIMEDOUT)($request);
    });

    // 8 s left: the try runs with a shorter timeout and its timeout says nothing about the site.
    expect($this->checker->checkAll(['slow.test'], CarbonImmutable::now()->addSeconds(8)))->toBe([])
        ->and($tries)->toBe(1);

    // A refusal is the site's answer even then; there is no time for a retry, so it stands.
    $checks = $this->checker->checkAll(['refused.test'], CarbonImmutable::now()->addSeconds(8));
    expect($checks['refused.test']['error'])->toBe($refused)->and($tries)->toBe(2);
    Sleep::assertNeverSlept();
});

it('sends monitoreo\'s user agent, or the configured one', function () {
    Http::fake();
    $this->checker->checkAll(['a.test'], ($this->deadline)());
    config(['newdesk.monitoring.sitecheck_user_agent' => 'Mozilla/5.0 (compatible; Monitoreo-Nexosmart/1.0)']);
    $this->checker->checkAll(['b.test'], ($this->deadline)());

    $agents = Http::recorded()->map(fn (array $pair) => $pair[0]->header('User-Agent')[0])->all();
    expect($agents)->toBe([SiteChecker::DEFAULT_USER_AGENT, 'Mozilla/5.0 (compatible; Monitoreo-Nexosmart/1.0)']);
});

it('says why there was no answer, and where a timeout got stuck (D-075)', function () {
    $timeout = fn (string $verbose) => SiteChecker::failureReason(['errno' => CURLE_OPERATION_TIMEDOUT], $verbose);
    // curl 8 verbose logs of each phase.
    $never = "* Connection timed out after 1512 milliseconds\n";
    $inTls = "* ALPN: curl offers http/1.1\n* SSL Trust Anchors:\n* Connection timed out after 1507 milliseconds\n";
    $secured = "* ALPN: curl offers http/1.1\n* SSL connection using TLSv1.3 / TLS_AES_256_GCM_SHA384\n* Operation timed out\n";
    $sent = "* Established connection to a.test (1.1.1.1 port 443)\n> GET / HTTP/1.1\nHost: a.test\n\n* Request completely sent off\n";

    expect($timeout($never))->toBe('timeout al conectar')
        ->and($timeout($inTls))->toBe('timeout en TLS')
        ->and($timeout("*   Trying 1.1.1.1:443...\n* Connected to a.test (1.1.1.1) port 443\n"))->toBe('timeout en TLS')
        ->and($timeout($secured))->toBe('timeout enviando el pedido')
        ->and($timeout($sent))->toBe('timeout esperando respuesta')
        ->and(SiteChecker::failureReason(['errno' => 60, 'error' => 'SSL certificate problem: self-signed certificate'], ''))->toBe('certificado inválido')
        ->and(SiteChecker::failureReason(['errno' => 6, 'error' => 'Could not resolve host: a.test'], ''))->toBe('DNS no resuelve')
        ->and(SiteChecker::failureReason(['errno' => 7, 'error' => "Couldn't connect to server"], "* connect to 1.1.1.1 port 443 failed: Connection refused\n"))->toBe('conexión rechazada')
        ->and(SiteChecker::failureReason(['errno' => 35, 'error' => 'OpenSSL/3.0.13: error:0A000458:SSL routines::tlsv1 unrecognized name'], ''))
        ->toBe('TLS: el edge no tiene certificado para este dominio')
        ->and(SiteChecker::failureReason(['errno' => 56, 'error' => str_repeat('x', 300)], ''))->toHaveLength(200);
});
