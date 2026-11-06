<?php

namespace App\Domain\Projects\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Shared\Casts\EncryptedFor;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int $client_id
 * @property string $name
 * @property string $code_prefix
 * @property string|null $description
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property ProjectStatus $status
 * @property int|null $leader_user_id
 * @property string|null $slug
 * @property string|null $primary_color
 * @property string|null $logo_path
 * @property string|null $logo_type
 * @property CarbonImmutable|null $logo_updated_at
 * @property string|null $domain
 * @property CarbonImmutable|null $domain_expires_at
 * @property CarbonImmutable|null $domain_expires_manual
 * @property CarbonImmutable|null $domain_checked_at
 * @property int $domain_notice_level
 * @property string|null $errors_url
 * @property string|null $errors_token
 * @property string|null $discord_channel_id
 * @property bool $portal_active
 */
#[UseFactory(ProjectFactory::class)]
class Project extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id', 'client_id', 'name', 'code_prefix', 'description', 'starts_on', 'ends_on',
        'status', 'leader_user_id',
        // RF-13.06/07 (monitoreo's portal_projects). The logo, the errors token and the domain lookups
        // are written by their own actions.
        'slug', 'primary_color', 'domain', 'domain_expires_manual', 'errors_url', 'discord_channel_id', 'portal_active',
    ];

    // Write-only: the API says `has_errors_token`, never the token.
    protected $hidden = ['errors_token'];

    protected $attributes = ['status' => 'active', 'portal_active' => true, 'domain_notice_level' => 0];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'status' => ProjectStatus::class,
            'logo_updated_at' => 'immutable_datetime',
            'domain_expires_at' => 'immutable_datetime',
            'domain_expires_manual' => 'immutable_date',
            'domain_checked_at' => 'immutable_datetime',
            'domain_notice_level' => 'integer',
            'errors_token' => EncryptedFor::class,
            'portal_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<User, $this> */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_user_id');
    }

    /** RF-03.03. The leader is always a member too. @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')->withPivot('created_at');
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    /** RF-03.03: only members see a project, unless they manage projects. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->hasPermission(Permission::ProjectsManage)
            ? $query
            : $query->whereHas('members', fn (Builder $members) => $members->whereKey($user->id));
    }
}
