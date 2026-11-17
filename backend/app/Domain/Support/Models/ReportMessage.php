<?php

namespace App\Domain\Support\Models;

use App\Domain\Shared\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One message of a report's chat (D-049), from the client or the team. An internal one is a
 * team-only note (D-051): the client never gets it, nor its images.
 *
 * @property int $id
 * @property int $company_id
 * @property int $report_id
 * @property string $author_kind
 * @property int|null $user_id
 * @property string $author
 * @property string $body
 * @property bool $internal
 * @property CarbonImmutable $created_at
 */
class ReportMessage extends Model
{
    use BelongsToCompany;

    public const CLIENT = 'client';

    public const TEAM = 'team';

    /** How the client sees whoever answers from the team (like the incident notes, D-042). */
    public const TEAM_AUTHOR = 'Equipo nexosmart';

    public const UPDATED_AT = null;

    protected $table = 'support_report_messages';

    protected $guarded = [];

    protected $dateFormat = Report::DATE_FORMAT;

    protected function casts(): array
    {
        return ['internal' => 'boolean', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Report, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /** @return HasMany<ReportAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ReportAttachment::class, 'message_id')->orderBy('id');
    }

    /** What the client may read: never an internal note. */
    public function scopeVisibleToClient(Builder $query): Builder
    {
        return $query->where('internal', false);
    }

    /**
     * monitoreo's ReportMessage JSON. $base is the report's API path the images hang from; the
     * client sees "Equipo nexosmart" instead of whoever on the team wrote. Needs `attachments`.
     *
     * @return array<string, mixed>
     */
    public function toPayload(string $base, bool $forClient): array
    {
        $payload = [
            'id' => $this->id,
            'author_kind' => $this->author_kind,
            'author' => $forClient && $this->author_kind === self::TEAM ? self::TEAM_AUTHOR : $this->author,
            'body' => $this->body,
            'created_at' => $this->created_at->toJSON(),
            'images' => $this->attachments->map(fn (ReportAttachment $attachment) => "{$base}/attachments/{$attachment->id}")->values()->all(),
        ];

        // Go's `internal,omitempty`: only a note says it.
        return $this->internal ? [...$payload, 'internal' => true] : $payload;
    }
}
