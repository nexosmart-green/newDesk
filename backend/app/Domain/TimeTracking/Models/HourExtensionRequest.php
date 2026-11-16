<?php

namespace App\Domain\TimeTracking\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\TimeTracking\Enums\HourExtensionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RF-05.08: one-click request from the "sin horas" block.
 *
 * @property int $id
 * @property int $project_id
 * @property int $requested_by
 * @property string|null $message
 * @property HourExtensionStatus $status
 */
class HourExtensionRequest extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected $attributes = ['status' => HourExtensionStatus::Pending->value];

    protected function casts(): array
    {
        return ['status' => HourExtensionStatus::class, 'resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isPending(): bool
    {
        return $this->status === HourExtensionStatus::Pending;
    }

    /**
     * @param  Builder<HourExtensionRequest>  $query
     * @return Builder<HourExtensionRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), HourExtensionStatus::Pending);
    }
}
