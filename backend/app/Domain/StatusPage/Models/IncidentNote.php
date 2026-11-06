<?php

namespace App\Domain\StatusPage\Models;

use App\Domain\Shared\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * What the team says about an incident ("Investigando…" → "Resuelto: …", D-042). The client sees
 * the text and the date; the author only the team.
 *
 * @property int $id
 * @property int $company_id
 * @property int $period_id
 * @property int|null $user_id
 * @property string $author
 * @property string $body
 * @property CarbonImmutable $created_at
 */
class IncidentNote extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $table = 'status_incident_notes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    /** monitoreo's NoteView; the author only for the team. @return array<string, mixed> */
    public function toPayload(bool $forTeam): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'created_at' => $this->created_at->toIso8601ZuluString(),
            ...($forTeam ? ['author' => $this->author] : []),
        ];
    }
}
