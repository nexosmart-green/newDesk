<?php

namespace App\Domain\Assistant\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A turn ended (answered or failed): its channel may relay it (Discord edits its "pensando…" message). */
final class AssistantTurnFinished
{
    use Dispatchable;

    public function __construct(public readonly int $answerId) {}
}
