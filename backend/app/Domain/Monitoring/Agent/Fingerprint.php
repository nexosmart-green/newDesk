<?php

namespace App\Domain\Monitoring\Agent;

/**
 * The agent certificate's SHA-256, as monitoreo stores it: lowercase hex, no separators.
 */
final class Fingerprint
{
    /** tlsutil.NormalizeFingerprint: accepts the common "AA:BB:..." notation too. */
    public static function normalize(?string $fingerprint): string
    {
        return strtolower(str_replace(':', '', trim((string) $fingerprint)));
    }

    public static function isValid(string $fingerprint): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $fingerprint) === 1;
    }
}
