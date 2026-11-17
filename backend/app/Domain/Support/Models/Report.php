<?php

namespace App\Domain\Support\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\Shared\Text;
use App\Domain\StatusPage\Status\Parts;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A "Reportar un problema" submission (RF-13.08, D-041) with its chat (D-049). The context the
 * back gathered when it came in (live check, open issues, recent errors, browser, server snapshot)
 * is stored as JSON, as written.
 *
 * @property int $id
 * @property int $company_id
 * @property int $project_id
 * @property int $client_id
 * @property int|null $user_id
 * @property string $user_email
 * @property string|null $part
 * @property string $urgency
 * @property string $description
 * @property string $status
 * @property string|null $screenshot_path
 * @property string|null $screenshot_type
 * @property int $screenshot_size
 * @property object $live_check
 * @property list<object> $open_issues
 * @property object|null $recent_errors
 * @property object $browser
 * @property object|null $server_snapshot
 * @property string $ip
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $last_client_message_at
 * @property CarbonImmutable|null $last_team_message_at
 * @property CarbonImmutable|null $client_seen_at
 * @property CarbonImmutable|null $team_seen_at
 */
class Report extends Model
{
    use BelongsToCompany;

    public const RECEIVED = 'received';

    public const IN_REVIEW = 'in_review';

    public const RESOLVED = 'resolved';

    public const STATUSES = [self::RECEIVED, self::IN_REVIEW, self::RESOLVED];

    /** The urgencies a client picks (D-041). */
    public const URGENCY_LABELS = ['blocking' => 'No puedo trabajar', 'annoying' => 'Molesta pero puedo seguir', 'question' => 'Consulta'];

    /** Microseconds, like monitoreo: read marks are compared with messages of the same second. */
    public const DATE_FORMAT = 'Y-m-d H:i:s.u';

    protected $table = 'support_reports';

    protected $guarded = [];

    protected $dateFormat = self::DATE_FORMAT;

    protected function casts(): array
    {
        return [
            'screenshot_size' => 'integer',
            'live_check' => 'object',
            'open_issues' => 'object',
            'recent_errors' => 'object',
            'browser' => 'object',
            'server_snapshot' => 'object',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'last_client_message_at' => 'immutable_datetime',
            'last_team_message_at' => 'immutable_datetime',
            'client_seen_at' => 'immutable_datetime',
            'team_seen_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<ReportMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ReportMessage::class);
    }

    /** The client wrote after the team last opened it. */
    public function unreadByTeam(): bool
    {
        return self::after($this->last_client_message_at, $this->team_seen_at);
    }

    /** The team wrote after the client last opened it. */
    public function unreadByClient(): bool
    {
        return self::after($this->last_team_message_at, $this->client_seen_at);
    }

    /** The part the client chose ("App"), null for "No sé". */
    public function partLabel(): ?string
    {
        return $this->part === null ? null : Parts::LABELS[$this->part] ?? null;
    }

    /**
     * Records that a side opened the chat. The team's mark is shared by the whole team, as in monitoreo.
     *
     * ponytail: one team mark for everyone; per-member read marks if that matters.
     */
    public function markSeen(string $side): void
    {
        $this->timestamps = false;
        $this->forceFill([$side === ReportMessage::CLIENT ? 'client_seen_at' : 'team_seen_at' => CarbonImmutable::now()->utc()])->save();
        $this->timestamps = true;
    }

    /** New reports plus those with client messages the team has not opened: the menu's counter (D-049). */
    public function scopeNeedingTeam(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query->where('status', self::RECEIVED)
            ->orWhere(fn (Builder $query) => $query->whereNotNull('last_client_message_at')
                ->where(fn (Builder $query) => $query->whereNull('team_seen_at')->orWhereColumn('last_client_message_at', '>', 'team_seen_at'))));
    }

    /** The chat, oldest first, with its images; internal notes only for the team. @return Collection<int, ReportMessage> */
    public function chat(bool $withInternal): Collection
    {
        return $this->messages()->unless($withInternal, fn ($query) => $query->visibleToClient())
            ->with('attachments')->orderBy('created_at')->orderBy('id')->get();
    }

    /** The API path the client reaches it by; its screenshot and images hang from it. */
    public static function clientPath(string $slug, int $id): string
    {
        return "api/portal/projects/{$slug}/reports/{$id}";
    }

    public static function teamPath(int $id): string
    {
        return "api/portal-admin/reports/{$id}";
    }

    /** monitoreo's ClientReport: what its client sees, no IP and no live details. @return array<string, mixed> */
    public function toClientPayload(string $slug): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at->toJSON(),
            'updated_at' => $this->updated_at->toJSON(),
            'resolved_at' => $this->resolved_at?->toJSON(),
            'urgency' => $this->urgency,
            'description' => $this->description,
            'component_label' => $this->partLabel(),
            'status' => $this->status,
            'unread' => $this->unreadByClient(),
            'screenshot_url' => $this->screenshot_path === null ? null : self::clientPath($slug, $this->id).'/screenshot',
        ];
    }

    /** monitoreo's ReportRow, a line of the team's queue. Needs `client` and `project`. @return array<string, mixed> */
    public function toRowPayload(): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at->toJSON(),
            'urgency' => $this->urgency,
            'status' => $this->status,
            'description' => Text::clip($this->description, 140),
            'client_id' => $this->client_id,
            'client_name' => $this->client->displayName(),
            'project_id' => $this->project_id,
            'project_name' => $this->project->name,
            'user_email' => $this->user_email === '' ? null : $this->user_email,
            'component_label' => $this->partLabel(),
            'has_screenshot' => $this->screenshot_path !== null,
            'unread' => $this->unreadByTeam(),
        ];
    }

    /**
     * monitoreo's ReportDetail: the row with everything the back gathered and the chat, notes included.
     *
     * @param  iterable<ReportMessage>  $messages
     * @return array<string, mixed>
     */
    public function toDetailPayload(iterable $messages): array
    {
        $base = self::teamPath($this->id);

        return [
            ...$this->toRowPayload(),
            'description' => $this->description,
            'updated_at' => $this->updated_at->toJSON(),
            'resolved_at' => $this->resolved_at?->toJSON(),
            'ip' => $this->ip,
            'browser' => $this->browser,
            'live_check' => $this->live_check,
            'open_issues' => $this->open_issues,
            'recent_errors' => $this->recent_errors,
            'screenshot_url' => $this->screenshot_path === null ? null : "{$base}/screenshot",
            'messages' => collect($messages)->map(fn (ReportMessage $message) => $message->toPayload($base, forClient: false))->values()->all(),
            'server_snapshot' => $this->server_snapshot,
        ];
    }

    private static function after(?CarbonImmutable $message, ?CarbonImmutable $seen): bool
    {
        return $message !== null && ($seen === null || $message->greaterThan($seen));
    }
}
