<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Domain\Identity\Actions\RequestPortalLoginCode;
use App\Domain\Identity\Actions\VerifyPortalLoginCode;
use App\Domain\Identity\Models\PortalLoginCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\RequestPortalCodeRequest;
use App\Http\Requests\Portal\VerifyPortalCodeRequest;
use App\Http\Resources\CurrentUserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Client portal login with a mailed code, no password (RF-13.07, ADR-0002 §7). The team uses
 * /api/v1/auth/login and gets the unknown-address answer here; logout is /api/v1/auth/logout.
 */
class PortalLoginController extends Controller
{
    /** The requesting browser's nonce: a code only works in the browser that asked for it (D-082). */
    private const LOGIN_COOKIE = 'portal_login';

    /** A browser that entered the account skips the shared code budget (D-114). */
    private const BROWSER_COOKIE = 'portal_browser';

    private const NONCE_LENGTH = 40;

    /** D-038: a client stays in for 30 days, without renewal. */
    private const SESSION_MINUTES = 30 * 24 * 60;

    private const KNOWN_BROWSER_MINUTES = 365 * 24 * 60;

    public function requestCode(RequestPortalCodeRequest $request, RequestPortalLoginCode $requestCode): JsonResponse
    {
        // The browser keeps its nonce across "resend code"; unknown addresses get one too, so the answer is identical.
        $nonce = $this->cookieValue($request, self::LOGIN_COOKIE);
        if ($nonce === null || strlen($nonce) !== self::NONCE_LENGTH) {
            $nonce = Str::random(self::NONCE_LENGTH);
        }

        $requestCode($request->validated('email'), $nonce, (string) $request->ip(), $this->cookieValue($request, self::BROWSER_COOKIE));

        return ApiResponse::ok(message: 'Si el mail está habilitado, te enviamos un código.')
            ->withCookie($this->cookie(self::LOGIN_COOKIE, $nonce, PortalLoginCode::TTL_MINUTES));
    }

    public function verifyCode(VerifyPortalCodeRequest $request, VerifyPortalLoginCode $verifyCode): JsonResponse
    {
        $user = $verifyCode($request->validated('email'), $request->validated('code'), $this->cookieValue($request, self::LOGIN_COOKIE));
        if ($user === null) {
            return ApiResponse::error('Código inválido o vencido.', 422, ['code' => ['Código inválido o vencido.']]);
        }

        // Sanctum's SPA session, plus a remember cookie that logs the client back in when the session
        // idles out. It lasts 30 days from this login and is never renewed (D-038).
        // ponytail: the 30 days are the cookie's, the server does not expire a copied cookie; logout or
        // deactivation cut it. Store an expiry with the remember token if that ever matters.
        /** @var SessionGuard $guard */
        $guard = Auth::guard('web');
        $guard->setRememberDuration(self::SESSION_MINUTES);
        $guard->login($user, remember: true);
        $request->session()->regenerate();

        return ApiResponse::ok(['next' => 'done', 'user' => CurrentUserResource::make($user)])
            ->withCookie(Cookie::forget(self::LOGIN_COOKIE))
            ->withCookie($this->cookie(self::BROWSER_COOKIE, RequestPortalLoginCode::knownBrowserToken($user), self::KNOWN_BROWSER_MINUTES));
    }

    private function cookieValue(Request $request, string $name): ?string
    {
        $value = $request->cookie($name);

        return is_string($value) ? $value : null;
    }

    private function cookie(string $name, string $value, int $minutes): SymfonyCookie
    {
        return Cookie::make($name, $value, $minutes, sameSite: 'strict');
    }
}
