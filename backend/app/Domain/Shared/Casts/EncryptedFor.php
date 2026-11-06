<?php

namespace App\Domain\Shared\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypted attribute bound to its table and column (ADR-0002 §5, H9 of monitoreo's audit):
 * the plaintext is sealed together with "table.column", so a ciphertext copied into another
 * column (or table) does not decrypt there.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class EncryptedFor implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $prefix = self::context($model, $key)."\0";
        $plain = Crypt::decryptString($value);
        if (! str_starts_with($plain, $prefix)) {
            throw new DecryptException("The value of {$model->getTable()}.{$key} was sealed for another column.");
        }

        return substr($plain, strlen($prefix));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Crypt::encryptString(self::context($model, $key)."\0".$value);
    }

    private static function context(Model $model, string $key): string
    {
        return $model->getTable().'.'.$key;
    }
}
