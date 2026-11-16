<?php

namespace App\Domain\Assistant\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * RF-13.05: a report file (xlsx/pdf) the assistant generated, on the private disk. Only its owner downloads it.
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property int $user_id
 * @property int|null $message_id
 * @property string $name
 * @property string $path
 * @property string $format
 * @property int $size
 * @property Carbon $expires_at
 */
class AssistantFile extends Model
{
    use BelongsToCompany;

    public const TTL_DAYS = 7;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
