<?php

namespace App\Domain\Assistant\Support;

/** What running a confirmed proposal produced: the domain action's result, or the reason it refused. */
final class ProposalOutcome
{
    /** @param  array<string, mixed>|null  $result */
    public function __construct(
        public readonly bool $succeeded,
        public readonly ?array $result,
        public readonly ?string $error,
    ) {}
}
