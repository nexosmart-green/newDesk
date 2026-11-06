<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A 6-digit code mailed to a client user (RF-13.07). Only its keyed hash is stored.
 *
 * @property int $id
 * @property int $user_id
 * @property string $code_hash
 * @property string $nonce_hash
 * @property int $attempts
 * @property string $ip
 * @property Carbon $created_at
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 */
class PortalLoginCode extends Model
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    /** Codes per account and hour, from any IP (D-081, D-087). */
    public const PER_ACCOUNT_HOURLY = 5;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'code_hash', 'nonce_hash', 'ip', 'expires_at', 'used_at'];

    protected $hidden = ['code_hash', 'nonce_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    /** HMAC keyed with the app key; the purpose keeps a code hash from matching a nonce hash. */
    public static function digest(string $purpose, string $value): string
    {
        return hash_hmac('sha256', $purpose."\0".$value, (string) config('app.key'));
    }

    public static function codeHash(int $userId, string $code): string
    {
        return self::digest('portal-login-code', $userId.':'.$code);
    }

    public static function nonceHash(string $nonce): string
    {
        return self::digest('portal-login-nonce', $nonce);
    }
}
