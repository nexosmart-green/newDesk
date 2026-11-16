<?php

namespace App\Domain\Scheduling\Support;

/**
 * What one pass of the recurring-shift generator did for one rule.
 */
final readonly class RecurrenceGeneration
{
    public const SKIPPED_HOLIDAY = 'holiday';

    public const SKIPPED_TAKEN = 'taken';

    /**
     * @param  list<string>  $dates  local days created
     * @param  list<array{date: string, reason: string}>  $skipped
     */
    public function __construct(public array $dates = [], public array $skipped = []) {}
}
