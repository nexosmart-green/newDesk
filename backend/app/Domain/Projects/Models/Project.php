<?php

namespace App\Domain\Projects\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Enums\ProjectType;
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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $client_id
 * @property string $name
 * @property string $code_prefix
 * @property string|null $description
 * @property ProjectType $type
 * @property ProjectStatus $status
 * @property int|null $leader_user_id
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property int $consumed_seconds closed time entries (cache)
 * @property int $allocated_seconds Σ hour_allocations (cache)
 * @property int $hour_alert_level last consumption alert sent (RF-05.07)
 * @property int $task_sequence
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
    use SoftDeletes;

    public const EDITABLE = ['client_id', 'name', 'code_prefix', 'description', 'type', 'leader_user_id', 'starts_on', 'ends_on'];

    // RF-13.06/07 (monitoreo's portal_projects). The logo, the errors token and the domain lookups
    // are written by their own actions.
    public const STATUS_PAGE = ['slug', 'primary_color', 'domain', 'domain_expires_manual', 'errors_url', 'discord_channel_id', 'portal_active'];

    protected $fillable = ['company_id', 'status', ...self::EDITABLE, ...self::STATUS_PAGE];

    // Write-only: the API says `has_errors_token`, never the token.
    protected $hidden = ['errors_token'];

    protected $attributes = ['status' => 'active', 'consumed_seconds' => 0, 'allocated_seconds' => 0, 'hour_alert_level' => 0, 'task_sequence' => 0,
        'portal_active' => true, 'domain_notice_level' => 0];

    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'status' => ProjectStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'consumed_seconds' => 'integer',
            'allocated_seconds' => 'integer',
            'hour_alert_level' => 'integer',
            'task_sequence' => 'integer',
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
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_user_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')->withPivot('added_at', 'added_by');
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    /** CA-03.01: once task codes circulate, the prefix is frozen. */
    public function prefixIsLocked(): bool
    {
        return $this->task_sequence > 0;
    }

    /** Projects the user may see: all with `projects.manage`, otherwise only where they are a member. */
    public function scopeVisibleTo(Builder $query, User $user, bool $seesAll): Builder
    {
        return $seesAll ? $query : $query->whereHas('members', fn (Builder $members) => $members->whereKey($user->id));
    }
}
