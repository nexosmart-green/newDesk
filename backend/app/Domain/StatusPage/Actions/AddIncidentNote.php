<?php

namespace App\Domain\StatusPage\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\StatusPage\Models\IncidentNote;
use App\Domain\StatusPage\Models\Period;
use App\Domain\StatusPage\Status\ComponentState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A note of the team on an incident ("Investigando…" → "Resuelto: …", D-042). Only a down period is
 * an incident; a degraded one does not exist as such.
 */
final class AddIncidentNote
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(Period $period, string $body, User $actor): IncidentNote
    {
        if ($period->status !== ComponentState::DOWN) {
            throw (new ModelNotFoundException)->setModel(Period::class, [$period->id]);
        }
        // Control characters out, except newlines and tabs.
        $text = trim((string) preg_replace('/[^\P{Cc}\n\t]/u', '', str_replace("\r\n", "\n", $body)));
        if ($text === '' || mb_strlen($text) > 2000) {
            throw ValidationException::withMessages(['body' => 'Escribí la nota (hasta 2000 caracteres).']);
        }

        return DB::transaction(function () use ($period, $text, $actor) {
            $note = IncidentNote::query()->create([
                'company_id' => $period->company_id, 'period_id' => $period->id, 'user_id' => $actor->id,
                'author' => $actor->email, 'body' => $text, 'created_at' => CarbonImmutable::now()->utc(),
            ]);
            ($this->recordActivity)('status_incident.note_added', $note, $actor, null, ['period_id' => $period->id]);

            return $note;
        });
    }
}
