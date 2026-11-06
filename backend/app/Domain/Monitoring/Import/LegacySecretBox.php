<?php

namespace App\Domain\Monitoring\Import;

use InvalidArgumentException;
use RuntimeException;

/**
 * Opens values sealed by monitoreo's secret.Box (internal/panel/secret): AES-256-GCM with
 * key = HMAC-SHA256(master, "monitoreo:encrypt"), stored as nonce(12) || ciphertext || tag(16),
 * bound to "table.column:id" as additional data (H9). Values sealed before H9 have none.
 */
final class LegacySecretBox
{
    private const NONCE = 12;

    private const TAG = 16;

    private readonly string $key;

    /** @param  string  $masterKey  monitoreo's ENCRYPTION_KEY (32 bytes, base64) */
    public function __construct(string $masterKey)
    {
        $master = base64_decode($masterKey, true);
        if ($master === false || strlen($master) !== 32) {
            throw new InvalidArgumentException('MONITOREO_ENCRYPTION_KEY debe ser la ENCRYPTION_KEY de monitoreo (32 bytes en base64).');
        }
        $this->key = hash_hmac('sha256', 'monitoreo:encrypt', $master, true);
    }

    /** secret.Row: what a sealed value is bound to. */
    public static function row(string $column, int $id): string
    {
        return "{$column}:{$id}";
    }

    public function decrypt(string $sealed, string $row): string
    {
        $plain = $this->open($sealed, $row) ?? ($row !== '' ? $this->open($sealed, '') : null);
        if ($plain === null) {
            throw new RuntimeException("No se pudo descifrar {$row}: ¿es la ENCRYPTION_KEY de monitoreo?");
        }

        return $plain;
    }

    private function open(string $sealed, string $additionalData): ?string
    {
        if (strlen($sealed) < self::NONCE + self::TAG) {
            return null;
        }
        $plain = openssl_decrypt(
            substr($sealed, self::NONCE, -self::TAG),
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            substr($sealed, 0, self::NONCE),
            substr($sealed, -self::TAG),
            $additionalData,
        );

        return $plain === false ? null : $plain;
    }
}
