<?php

namespace App\Domain\Assistant\Models;

use App\Domain\Assistant\Enums\AssistantChannel;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * RF-14.01: one conversation of one person with the assistant = one Claude Code session (`--resume`).
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property AssistantChannel $channel
 * @property string|null $title
 * @property string|null $claude_session_id
 * @property Carbon|null $last_message_at
 */
class AssistantConversation extends Model
{
    use BelongsToCompany;

    public const TITLE_MAX = 120;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['channel' => AssistantChannel::class, 'last_message_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<AssistantMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class, 'conversation_id');
    }
}
