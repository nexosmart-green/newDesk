<?php

namespace App\Domain\Shared\Activity;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Immutable: rows are only ever inserted (RNF-07).
 */
class ActivityLog extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $table = 'activity_log';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'source' => ActivitySource::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('activity_log is append-only'));
        static::deleting(fn () => throw new LogicException('activity_log is append-only'));
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
