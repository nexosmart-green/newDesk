<?php

namespace App\Domain\TimeTracking\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Local dates (Y-m-d, both days included) turned into the UTC instants `started_at` is stored in:
 * [from 00:00 local, the day after `to` 00:00 local). Comparing local date strings against UTC
 * would move late-evening entries to the next day west of UTC.
 */
final class LocalDateRange
{
    private function __construct(
        public readonly ?Carbon $fromUtc,
        public readonly ?Carbon $toExclusiveUtc,
    ) {}

    /** Either end may be null: the range is open on that side. */
    public static function of(?string $from, ?string $to, string $timezone): self
    {
        return new self(
            filled($from) ? Carbon::parse($from, $timezone)->startOfDay()->utc() : null,
            filled($to) ? Carbon::parse($to, $timezone)->addDay()->startOfDay()->utc() : null,
        );
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, string $column = 'time_entries.started_at'): Builder
    {
        return $query
            ->when($this->fromUtc, fn (Builder $q, Carbon $from) => $q->where($column, '>=', $from))
            ->when($this->toExclusiveUtc, fn (Builder $q, Carbon $to) => $q->where($column, '<', $to));
    }
}
