<?php

namespace App\Domain\Identity\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Companies\Models\Company;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Users are not company-scoped by a global scope (the authenticated user is resolved
 * before any scope could apply); query them through `$company->users()` instead.
 *
 * @property int $id
 * @property int $company_id
 * @property int $role_id
 * @property int|null $client_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property UserStatus $status
 * @property string $locale
 * @property string|null $timezone
 */
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'company_id', 'role_id', 'client_id', 'first_name', 'last_name', 'email', 'password',
        'status', 'locale', 'timezone',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** Team by default; a freshly created model must answer isClient() without a reload. */
    protected $attributes = ['client_id' => null];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
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

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withoutGlobalScopes();
    }

    /** A client user only reaches the portal (RF-13.07, CA-13.14). */
    public function isClient(): bool
    {
        return $this->client_id !== null;
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
}
