<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RF-14.08: Discord signs every interaction (Ed25519 over timestamp + raw body) with the app's public key.
 * Anything not signed by Discord is refused before it reaches the assistant; Discord itself checks that bad
 * signatures get a 401 before it accepts the endpoint URL.
 */
class VerifyDiscordSignature
{
    /** Older than this, a signed request is treated as a replay. */
    private const MAX_AGE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $publicKey = (string) config('newdesk.assistant.discord.public_key');
        $signature = (string) $request->header('X-Signature-Ed25519');
        $timestamp = (string) $request->header('X-Signature-Timestamp');

        if (! $this->valid($publicKey, $signature, $timestamp, $request->getContent())) {
            return response()->json(['error' => 'invalid request signature'], 401);
        }

        return $next($request);
    }

    private function valid(string $publicKey, string $signature, string $timestamp, string $body): bool
    {
        if (! ctype_xdigit($publicKey) || strlen($publicKey) !== 64 || ! ctype_xdigit($signature) || strlen($signature) !== 128
            || ! ctype_digit($timestamp) || abs(now()->timestamp - (int) $timestamp) > self::MAX_AGE_SECONDS) {
            return false;
        }

        return sodium_crypto_sign_verify_detached(hex2bin($signature), $timestamp.$body, hex2bin($publicKey));
    }
}
