<?php

namespace App\Domain\Assistant\Jobs;

use App\Domain\Assistant\Actions\AbandonAssistantTurn;
use App\Domain\Assistant\Actions\AnswerAssistantMessage;
use App\Domain\Assistant\Models\AssistantMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * One assistant turn on the `assistant` queue (a single worker: one turn at a time, RF-14.09). Never retried:
 * a failed turn says so instead of running twice.
 */
final class RunAssistantTurn implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const QUEUE = 'assistant';

    public int $tries = 1;

    /**
     * Above the worst case of a turn (the engine's timeout, plus one quick fresh retry when a stored session is
     * gone), so the turn closes itself; if the worker still kills it, failed() closes it.
     */
    public int $timeout = 300;

    public function __construct(public readonly int $answerId)
    {
        $this->onQueue(self::QUEUE);
    }

    public function handle(AnswerAssistantMessage $answer): void
    {
        $message = AssistantMessage::query()->withoutGlobalScopes()->find($this->answerId);
        if ($message !== null) {
            $answer($message);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $message = AssistantMessage::query()->withoutGlobalScopes()->find($this->answerId);
        if ($message !== null) {
            app(AbandonAssistantTurn::class)($message, AbandonAssistantTurn::STUCK);
        }
    }
}
