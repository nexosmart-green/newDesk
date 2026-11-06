<?php

namespace App\Domain\Companies\Models;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $tax_id
 * @property string|null $logo_path
 * @property string $currency
 * @property string $timezone
 * @property array<string, mixed>|null $settings
 */
#[UseFactory(CompanyFactory::class)]
class Company extends Model
{
    use HasFactory;

    /** RF-11.03 defaults. Missing keys in `settings` fall back to these. */
    public const DEFAULT_SETTINGS = [
        'manual_entry_days_back' => 7,
        'forgotten_timer_max_hours' => 10,
        'forgotten_timer_after_shift_hours' => 1,
        'hour_alert_thresholds' => [80, 100],
    ];

    protected $fillable = ['name', 'tax_id', 'logo_path', 'currency', 'timezone', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function setting(string $key): mixed
    {
        return ($this->settings ?? [])[$key] ?? self::DEFAULT_SETTINGS[$key] ?? null;
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Role, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class)->withoutGlobalScopes();
    }
}
