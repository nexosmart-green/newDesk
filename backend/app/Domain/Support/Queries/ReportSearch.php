<?php

namespace App\Domain\Support\Queries;

use App\Domain\Support\Models\Report;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** The MCP's reads of the report queue (monitoreo FindReports and ReportCountsByProject, D-050). */
final class ReportSearch
{
    /**
     * Newest first, with the total that matched. $projectIds null = any project, [] = none.
     *
     * @param  list<int>|null  $projectIds
     * @return array{0: Collection<int, Report>, 1: int}
     */
    public function find(?array $projectIds, string $client, string $status, string $urgency, bool $unread, ?CarbonImmutable $since, int $limit, int $offset): array
    {
        if ($projectIds === []) {
            return [new Collection, 0];
        }
        $query = Report::query()
            ->when($projectIds !== null, fn (Builder $query) => $query->whereIn('project_id', $projectIds))
            ->when($client !== '', fn (Builder $query) => $query->whereHas('client', fn (Builder $query) => $query->where('legal_name', 'like', "%{$client}%")->orWhere('trade_name', 'like', "%{$client}%")))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($urgency !== '', fn (Builder $query) => $query->where('urgency', $urgency))
            ->when($unread, fn (Builder $query) => $query->whereNotNull('last_client_message_at')
                ->where(fn (Builder $query) => $query->whereNull('team_seen_at')->orWhereColumn('last_client_message_at', '>', 'team_seen_at')))
            ->when($since !== null, fn (Builder $query) => $query->where('created_at', '>=', $since));
        $total = $query->count();

        return [$query->with(['client', 'project'])->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->offset($offset)->get(), $total];
    }

    /** Per project: reports not resolved yet and those with client messages the team has not opened. @return array<int, array{open: int, unread: int}> */
    public function countsByProject(): array
    {
        return Report::query()->toBase()->groupBy('project_id')
            ->selectRaw("project_id, SUM(status <> 'resolved') AS open, SUM(last_client_message_at IS NOT NULL AND (team_seen_at IS NULL OR last_client_message_at > team_seen_at)) AS unread")
            ->get()->mapWithKeys(fn (object $row) => [(int) $row->project_id => ['open' => (int) $row->open, 'unread' => (int) $row->unread]])->all();
    }
}
