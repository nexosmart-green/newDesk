<?php

namespace App\Domain\Assistant\Models;

use App\Domain\Assistant\Enums\ProposalStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * RF-13.02: a write the assistant prepared (validated, authorized, described) but did NOT run. Only the person
 * who asked can confirm it; confirming re-prepares it with their role at that moment and runs the domain action.
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int $message_id
 * @property string $tool
 * @property array<string, mixed> $arguments
 * @property string $summary
 * @property ProposalStatus $status
 * @property array<string, mixed>|null $result
 * @property string|null $error
 * @property string|null $resolved_channel
 * @property Carbon|null $resolved_at
 * @property Carbon $expires_at
 */
class AssistantProposal extends Model
{
    use BelongsToCompany;

    public const TTL_HOURS = 24;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'result' => 'array',
            'status' => ProposalStatus::class,
            'resolved_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AssistantMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(AssistantMessage::class, 'message_id');
    }

    public function isPending(): bool
    {
        return $this->status === ProposalStatus::Pending;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
