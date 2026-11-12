<?php

namespace App\Domain\Identity\Models;

use App\Domain\Companies\Models\Company;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Notifications\ResetPasswordNotification;
use App\Domain\People\Models\EmployeeProfile;
use App\Domain\People\Models\HourlyRate;
use App\Domain\People\Models\Skill;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Users are not company-scoped by a global scope (the authenticated user is resolved
 * before any scope could apply); query them through `$company->users()` instead.
 *
 * @property int $id
 * @property int $company_id
 * @property int $role_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property UserStatus $status
 * @property string $locale
 * @property string|null $timezone
 * @property string|null $two_factor_secret
 * @property list<string>|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 */
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'company_id', 'role_id', 'first_name', 'last_name', 'email', 'password',
        'status', 'locale', 'timezone',
    ];

    /** Mirrors the column defaults so fresh instances behave like reloaded ones. */
    protected $attributes = [
        'locale' => 'es',
        'timezone' => null,
        'avatar_path' => null,
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
        'last_login_at' => null,
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /** RF-01.02: mandatory for Admin (the system role). */
    public function requiresTwoFactor(): bool
    {
        return (bool) $this->role?->is_system;
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        // Roles are resolved for this user's company explicitly, not via the auth-based scope.
        return $this->belongsTo(Role::class)->withoutGlobalScopes();
    }

    /** @return HasOne<EmployeeProfile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class)->withoutGlobalScopes();
    }

    /** @return BelongsToMany<Skill, $this> */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'user_skills')->withPivot('level')->withoutGlobalScopes();
    }

    /** @return HasMany<HourlyRate, $this> */
    public function hourlyRates(): HasMany
    {
        return $this->hasMany(HourlyRate::class)->withoutGlobalScopes();
    }

    /** "Mi equipo" (RF-01.07): people whose profile reports to this user. */
    public function directReports(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, EmployeeProfile::class, 'reports_to_user_id', 'id', 'id', 'user_id')
            ->withoutGlobalScopes();
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), UserStatus::Active);
    }

    /**
     * Evaluated against the CURRENT role on every request (RF-12.03): changing a role
     * changes what the user's web session and MCP tokens can do immediately.
     */
    public function hasPermission(Permission $permission): bool
    {
        return $this->isActive() && $this->role !== null && $this->role->grants($permission);
    }

    public function effectiveTimezone(): string
    {
        return $this->timezone ?? $this->company->timezone;
    }

    /** Today's date (Y-m-d) where the person is: due dates and "overdue" are judged on this day. */
    public function localDate(): string
    {
        return now($this->effectiveTimezone())->toDateString();
    }
}
