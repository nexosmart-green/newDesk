<?php

namespace App\Domain\Assistant\Jobs;

use App\Domain\Assistant\Actions\AnswerAssistantMessage;
use App\Domain\Assistant\Models\AssistantMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * One assistant turn on the `assistant` queue (a single worker: one turn at a time, RF-13.09). Never retried:
 * a failed turn says so instead of running twice.
 */
final class RunAssistantTurn implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const QUEUE = 'assistant';

    public int $tries = 1;

    /** Above the engine's own timeout, so the turn closes (and revokes its token) before the worker kills it. */
    public int $timeout = 240;

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
}
