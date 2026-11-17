<?php

namespace App\Domain\Support\Queries;

use App\Domain\Identity\Models\User;
use App\Domain\Support\Models\Report;
use App\Domain\Support\Models\RoadmapItem;

/** A developer's own roadmap of reports, in order, as queue rows (D-064, D-066, CA-13.19). */
final class Roadmap
{
    /** @return list<array<string, mixed>> */
    public function __invoke(User $user): array
    {
        $ids = RoadmapItem::query()->where('user_id', $user->id)->orderBy('position')->orderBy('created_at')->orderBy('report_id')->pluck('report_id');
        $reports = Report::query()->with(['client', 'project'])->whereIn('id', $ids)->get()->keyBy('id');

        return $ids->map(fn (int $id) => $reports->get($id)?->toRowPayload())->filter()->values()->all();
    }
}
