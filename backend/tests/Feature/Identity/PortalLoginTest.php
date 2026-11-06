<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Actions\RequestPortalLoginCode;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\PortalLoginCode;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PortalLoginCodeNotification;
use Carbon\CarbonInterval;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** One browser of the SPA: its own IP and cookies, kept between requests as a real browser would. */
final class PortalBrowser
{
    /** @var array<string, string> */
    public array $cookies = [];

    public function __construct(private readonly TestCase $test, public string $ip = '127.0.0.1') {}

    public function requestCode(string $email): TestResponse
    {
        return $this->send('POST', '/api/v1/portal/auth/request-code', ['email' => $email]);
    }

    public function verifyCode(string $email, string $code): TestResponse
    {
        return $this->send('POST', '/api/v1/portal/auth/verify-code', ['email' => $email, 'code' => $code]);
    }

    /** @param  array<string, string>  $body */
    public function send(string $method, string $uri, array $body = []): TestResponse
    {
        $key = app('encrypter')->getKey();
        $cookies = array_map(
            fn (string $name) => encrypt(CookieValuePrefix::create($name, $key).$this->cookies[$name], false),
            array_combine(array_keys($this->cookies), array_keys($this->cookies)),
        );

        $response = $this->test->call($method, $uri, $body, $cookies, [], [
            'REMOTE_ADDR' => $this->ip,
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_REFERER' => 'http://localhost:5173/portal/login',
        ]);

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->isCleared()) {
                unset($this->cookies[$cookie->getName()]);
            } else {
                $this->cookies[$cookie->getName()] = (string) $response->getCookie($cookie->getName())->getValue();
            }
        }

        return $response;
    }
}

function portalClientUser(string $email = 'dueno@playinn.com', string $clientName = 'Playinn', array $attributes = []): User
{
    $admin = User::query()->where('email', 'admin@nexosmart.com')->first() ?? provisionCompany('admin@nexosmart.com');
    $client = Client::query()->withoutGlobalScopes()->firstOrCreate(['company_id' => $admin->company_id, 'name' => $clientName]);

    // Portal users have no password: they always enter with a mailed code.
    return User::factory()->forRole(roleNamed($admin, DefaultRoles::CLIENT))
        ->create(['email' => $email, 'client_id' => $client->id, 'password' => null, ...$attributes])
        ->fresh();
}

function lastPortalCode(User $user): string
{
    $sent = Notification::sent($user, PortalLoginCodeNotification::class);
    expect($sent)->not->toBeEmpty();

    return $sent->last()->code;
}

function portalCodesMailed(User $user): int
{
    return Notification::sent($user, PortalLoginCodeNotification::class)->count();
}

beforeEach(function () {
    Notification::fake();
    Sleep::fake();
});

it('logs a client user in with the mailed code and keeps them in for 30 days (CA-13.15, D-038)', function () {
    $user = portalClientUser();
    $browser = new PortalBrowser($this);

    $nonce = $browser->requestCode('Dueno@Playinn.com')
        ->assertOk()
        ->assertExactJson(['data' => null, 'message' => 'Si el mail está habilitado, te enviamos un código.', 'errors' => null])
        ->getCookie('portal_login', false);
    expect($nonce->isHttpOnly())->toBeTrue()
        ->and($nonce->getSameSite())->toBe('strict')
        ->and($nonce->getExpiresTime() - now()->getTimestamp())->toBeBetween(590, 610);

    $code = lastPortalCode($user);
    expect($code)->toMatch('/^\d{6}$/')
        ->and(PortalLoginCode::query()->value('code_hash'))->not->toContain($code);
    Notification::assertSentTo($user, PortalLoginCodeNotification::class, function (PortalLoginCodeNotification $mail) use ($user, $code) {
        $text = implode("\n", $mail->toMail($user)->introLines);

        return $mail->queue === 'mail' && str_contains($text, $code) && str_contains($text, 'mismo navegador');
    });

    $this->travel(5)->minutes();
    $response = $browser->verifyCode('dueno@playinn.com', $code)
        ->assertOk()
        ->assertJsonPath('data.next', 'done')
        ->assertJsonPath('data.user.email', 'dueno@playinn.com')
        ->assertJsonPath('data.user.client', ['id' => $user->client_id, 'name' => 'Playinn']);

    $knownBrowser = $response->getCookie('portal_browser', false);
    expect($browser->cookies)->not->toHaveKey('portal_login')->toHaveKey('portal_browser')
        ->and($knownBrowser->isHttpOnly())->toBeTrue()
        ->and($knownBrowser->getSameSite())->toBe('strict')
        ->and($knownBrowser->getExpiresTime() - now()->getTimestamp())->toBeGreaterThan(364 * 86400)
        ->and($user->fresh()->last_login_at)->not->toBeNull();

    $remember = $response->getCookie(Auth::guard('web')->getRecallerName(), false);
    expect($remember->getExpiresTime() - now()->getTimestamp())->toBeBetween(30 * 86400 - 60, 30 * 86400 + 60);

    // Once the session idles out, the remember cookie alone brings the client back.
    Auth::forgetGuards();
    $browser->cookies = [Auth::guard('web')->getRecallerName() => $browser->cookies[Auth::guard('web')->getRecallerName()]];
    $browser->send('GET', '/api/v1/me')->assertOk()->assertJsonPath('data.client.name', 'Playinn');
});

it('answers an unknown, team, inactive or look-alike address exactly like a known one, and mails nothing (D-076, D-077)', function () {
    portalClientUser('ana@example.com');
    portalClientUser('baja@playinn.com', attributes: ['status' => 'inactive']);
    portalClientUser('viejo@retirado.com', 'Retirado')->client->update(['active' => false]);

    foreach (['nadie@example.com', 'admin@nexosmart.com', 'baja@playinn.com', 'viejo@retirado.com', 'ana@exámple.com'] as $i => $email) {
        $browser = new PortalBrowser($this, "192.0.2.{$i}");
        $browser->requestCode($email)
            ->assertOk()
            ->assertExactJson(['data' => null, 'message' => 'Si el mail está habilitado, te enviamos un código.', 'errors' => null]);
        expect($browser->cookies)->toHaveKey('portal_login');

        $browser->verifyCode($email, '123456')
            ->assertUnprocessable()
            ->assertExactJson(['data' => null, 'message' => 'Código inválido o vencido.', 'errors' => ['code' => ['Código inválido o vencido.']]]);
    }

    Notification::assertNothingSent();
    expect(PortalLoginCode::query()->count())->toBe(0);

    (new PortalBrowser($this))->requestCode('no-es-mail')
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Ingresá un mail válido.');
});

it('pads every answer to the same minimum time, known address or not (D-077)', function () {
    portalClientUser();

    (new PortalBrowser($this, '192.0.2.1'))->requestCode('nadie@example.com');
    (new PortalBrowser($this, '192.0.2.2'))->requestCode('dueno@playinn.com');

    Sleep::assertSlept(fn (CarbonInterval $wait) => $wait->totalMicroseconds > 300_000, 2);
});

it('only accepts the code in the browser that asked for it (D-082)', function () {
    $user = portalClientUser();
    $victim = new PortalBrowser($this, '198.51.100.10');
    $victim->requestCode('dueno@playinn.com')->assertOk();
    $code = lastPortalCode($user);

    // A stranger who knows the address can neither burn the victim's attempts nor replace the code.
    $stranger = new PortalBrowser($this, '203.0.113.66');
    $stranger->requestCode('dueno@playinn.com')->assertOk();
    foreach (range(0, 4) as $i) {
        $stranger->verifyCode('dueno@playinn.com', "99999{$i}")->assertUnprocessable();
    }
    $stranger->verifyCode('dueno@playinn.com', $code)->assertUnprocessable();

    // Without the browser's cookie the code is never even looked up.
    $stranger->cookies = [];
    $stranger->verifyCode('dueno@playinn.com', $code)->assertUnprocessable();

    $victim->verifyCode('dueno@playinn.com', $code)->assertOk()->assertJsonPath('data.next', 'done');
});

it('expires a code after 10 minutes and keeps only the newest code of a browser (CA-13.15)', function () {
    $user = portalClientUser();
    $browser = new PortalBrowser($this);

    $browser->requestCode('dueno@playinn.com');
    $expired = lastPortalCode($user);
    $this->travel(11)->minutes();
    $browser->verifyCode('dueno@playinn.com', $expired)->assertUnprocessable();

    $browser->requestCode('dueno@playinn.com');
    $replaced = lastPortalCode($user);
    $this->travel(2)->minutes();
    $browser->requestCode('dueno@playinn.com');
    $newest = lastPortalCode($user);

    $browser->verifyCode('dueno@playinn.com', $replaced)->assertUnprocessable();
    $browser->verifyCode('dueno@playinn.com', $newest)->assertOk();
    $browser->verifyCode('dueno@playinn.com', $newest)->assertUnprocessable(); // spent
});

it('counts each guess before comparing it and kills the code after 5 (D-079)', function () {
    $user = portalClientUser();
    $browser = new PortalBrowser($this);
    $browser->requestCode('dueno@playinn.com');
    $code = lastPortalCode($user);
    $wrong = str_pad((string) (((int) $code + 1) % 1_000_000), 6, '0', STR_PAD_LEFT);

    foreach (range(1, 12) as $i) {
        $browser->ip = "198.51.100.{$i}"; // past the per-IP verify limit
        $browser->verifyCode('dueno@playinn.com', $wrong)->assertUnprocessable();
    }

    expect(PortalLoginCode::query()->value('attempts'))->toBe(PortalLoginCode::MAX_ATTEMPTS);
    $browser->verifyCode('dueno@playinn.com', $code)->assertUnprocessable();
});

it('mails at most 5 codes per hour per account from any IP, counted in the database (D-081, D-087)', function () {
    $user = portalClientUser();

    foreach (range(1, 5) as $i) {
        (new PortalBrowser($this, "192.0.2.{$i}"))->requestCode('dueno@playinn.com')->assertOk();
    }
    expect(portalCodesMailed($user))->toBe(5);

    // A sixth from a known IP and from a new one, after a restart that forgets every in-memory limit.
    $this->travel(2)->minutes();
    Cache::flush();
    (new PortalBrowser($this, '192.0.2.1'))->requestCode('dueno@playinn.com')->assertOk();
    (new PortalBrowser($this, '192.0.2.9'))->requestCode('dueno@playinn.com')->assertOk();
    expect(portalCodesMailed($user))->toBe(5);

    $this->travel(1)->hours();
    (new PortalBrowser($this, '192.0.2.9'))->requestCode('dueno@playinn.com')->assertOk();
    expect(portalCodesMailed($user))->toBe(6);
});

it('lets a browser that already entered the account get codes while a stranger spends the budget (D-114)', function () {
    $user = portalClientUser();
    $other = portalClientUser('otro@playinn.com');
    $owner = new PortalBrowser($this, '198.51.100.20');
    $owner->requestCode('dueno@playinn.com');
    $owner->verifyCode('dueno@playinn.com', lastPortalCode($user))->assertOk(); // 1 code of the 5
    $owner->send('POST', '/api/v1/auth/logout')->assertOk();
    expect($owner->cookies)->toHaveKey('portal_browser');

    foreach (range(1, 4) as $i) { // the stranger spends the other 4
        (new PortalBrowser($this, "203.0.113.{$i}"))->requestCode('dueno@playinn.com')->assertOk();
    }
    $sent = portalCodesMailed($user);

    $forgeries = [
        'a new browser' => null,
        'a forged token' => "{$user->id}.abc",
        "another account's token" => RequestPortalLoginCode::knownBrowserToken($other),
        'a tampered token' => RequestPortalLoginCode::knownBrowserToken($user).'x',
    ];
    foreach (array_values($forgeries) as $i => $token) {
        $browser = new PortalBrowser($this, '198.51.100.'.(30 + $i));
        $browser->cookies = $token === null ? [] : ['portal_browser' => $token];
        $browser->requestCode('dueno@playinn.com')->assertOk();
    }
    expect(portalCodesMailed($user))->toBe($sent);

    $this->travel(2)->minutes(); // past the 1/min of the owner's own address and IP
    Auth::forgetGuards();
    $owner->requestCode('dueno@playinn.com')->assertOk();
    expect(portalCodesMailed($user))->toBe($sent + 1);
    $owner->verifyCode('dueno@playinn.com', lastPortalCode($user))->assertOk();
});

it('throttles code requests per address and per IP', function () {
    portalClientUser();
    $browser = new PortalBrowser($this);

    $browser->requestCode('dueno@playinn.com')->assertOk();
    $browser->requestCode('dueno@playinn.com')->assertTooManyRequests();

    foreach (range(1, 19) as $i) {
        $browser->requestCode("x{$i}@example.com")->assertOk();
    }
    $browser->requestCode('last@example.com')->assertTooManyRequests();
});

it('signs the client users out when their client is inactivated (D-038)', function () {
    $user = portalClientUser();
    $this->actingAs($user)->getJson('/api/v1/me')->assertOk();

    $user->client->update(['active' => false]);

    $this->actingAs($user->fresh())->getJson('/api/v1/me')->assertUnauthorized();
});
