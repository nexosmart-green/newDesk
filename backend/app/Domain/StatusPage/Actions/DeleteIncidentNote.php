<?php

namespace App\Domain\StatusPage\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\StatusPage\Models\IncidentNote;
use Illuminate\Support\Facades\DB;

final class DeleteIncidentNote
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(IncidentNote $note, User $actor): void
    {
        DB::transaction(function () use ($note, $actor) {
            ($this->recordActivity)('status_incident.note_deleted', $note, $actor, $note->only(['period_id', 'body']));
            $note->delete();
        });
    }
}
