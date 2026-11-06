<?php

namespace App\Domain\Projects\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Enums\ProjectStatus;
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
 */
#[UseFactory(ProjectFactory::class)]
class Project extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id', 'client_id', 'name', 'code_prefix', 'description', 'starts_on', 'ends_on',
        'status', 'leader_user_id',
    ];

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'status' => ProjectStatus::class,
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
