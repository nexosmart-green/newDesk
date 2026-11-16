<?php

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\MessageStatus;
use App\Domain\Assistant\Enums\ProposalStatus;
use App\Domain\Assistant\Models\AssistantFile;
use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Assistant\Models\AssistantProposal;
use App\Domain\Assistant\Support\AssistantToken;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The scheduled sweep (assistant:housekeeping): no answer stays "pensando…" forever, proposals expire, report
 * files with people's hours don't outlive their week, and no assistant token outlives its turn.
 */
final class CleanUpAssistant
{
    /** A running turn is over well before this (RunAssistantTurn::$timeout = 5 min). */
    public const RUNNING_STUCK_MINUTES = 10;

    /** Waiting in the queue behind other turns (one worker) is normal; this long is not. */
    public const PENDING_STUCK_MINUTES = 30;

    public function __construct(private readonly AbandonAssistantTurn $abandon) {}

    /** @return array{turns: int, proposals: int, files: int, tokens: int} */
    public function __invoke(): array
    {
        return [
            'turns' => $this->abandonStuckTurns(),
            'proposals' => AssistantProposal::query()->withoutGlobalScopes()->where('status', ProposalStatus::Pending)
                ->where('expires_at', '<', now())->update(['status' => ProposalStatus::Expired, 'resolved_at' => now()]),
            'files' => $this->purgeExpiredFiles(),
            'tokens' => PersonalAccessToken::query()->where('name', 'like', AssistantToken::NAME_PREFIX.'%')
                ->where('expires_at', '<', now())->delete(),
        ];
    }

    private function abandonStuckTurns(): int
    {
        $stuck = AssistantMessage::query()->withoutGlobalScopes()
            ->where(fn ($query) => $query
                ->where(fn ($running) => $running->where('status', MessageStatus::Running)->where('updated_at', '<', now()->subMinutes(self::RUNNING_STUCK_MINUTES)))
                ->orWhere(fn ($pending) => $pending->where('status', MessageStatus::Pending)->where('created_at', '<', now()->subMinutes(self::PENDING_STUCK_MINUTES))))
            ->get();
        $stuck->each(fn (AssistantMessage $answer) => ($this->abandon)($answer, AbandonAssistantTurn::STUCK));

        return $stuck->count();
    }

    private function purgeExpiredFiles(): int
    {
        $expired = AssistantFile::query()->withoutGlobalScopes()->where('expires_at', '<', now())->get();
        $expired->each(function (AssistantFile $file) {
            Storage::disk(GenerateAssistantReport::DISK)->delete($file->path);
            $file->delete();
        });

        return $expired->count();
    }
}
