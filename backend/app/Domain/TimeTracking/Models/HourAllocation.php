<?php

namespace App\Domain\TimeTracking\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line of a project's hour bag (RF-05.04). The first is the initial load, the rest are extensions.
 * Insert-only: a mistake is corrected with another (negative) line, never by editing.
 *
 * @property int $id
 * @property int $project_id
 * @property int $seconds
 * @property string $reason
 * @property string|null $reference
 * @property int $created_by
 */
class HourAllocation extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['seconds' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('hour_allocations is append-only'));
        static::deleting(fn () => throw new LogicException('hour_allocations is append-only'));
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
