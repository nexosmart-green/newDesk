<?php

namespace App\Domain\Assistant\Models;

use App\Domain\Assistant\Enums\MessageRole;
use App\Domain\Assistant\Enums\MessageStatus;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A question of the person or an answer of the assistant. Answers carry the (estimated) cost of their turn,
 * which feeds the monthly cap (RF-13.09).
 *
 * @property int $id
 * @property int $company_id
 * @property int $conversation_id
 * @property MessageRole $role
 * @property string|null $content
 * @property MessageStatus $status
 * @property string|null $error
 * @property string $cost_usd
 * @property int|null $duration_ms
 * @property int|null $access_token_id
 * @property Carbon $created_at
 */
class AssistantMessage extends Model
{
    use BelongsToCompany;

    public const CONTENT_MAX = 4000;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['role' => MessageRole::class, 'status' => MessageStatus::class, 'cost_usd' => 'decimal:6'];
    }

    /** @return BelongsTo<AssistantConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'conversation_id');
    }

    /** @return HasMany<AssistantProposal, $this> */
    public function proposals(): HasMany
    {
        return $this->hasMany(AssistantProposal::class, 'message_id');
    }

    /** @return HasMany<AssistantFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(AssistantFile::class, 'message_id');
    }
}
