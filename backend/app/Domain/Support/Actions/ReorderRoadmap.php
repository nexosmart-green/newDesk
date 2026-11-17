<?php

namespace App\Domain\Support\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Support\Models\RoadmapItem;
use Illuminate\Support\Facades\DB;

/**
 * Sets the order of the user's own roadmap (D-064, D-065). The list must name exactly the reports on
 * it: anything else means it changed in another tab (409), and the browser goes back to what it had.
 */
final class ReorderRoadmap
{
    /** @param  list<int>  $reportIds */
    public function __invoke(User $user, array $reportIds): void
    {
        DB::transaction(function () use ($user, $reportIds) {
            $current = RoadmapItem::query()->where('user_id', $user->id)->lockForUpdate()->pluck('report_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $wanted = collect($reportIds)->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($current !== $wanted) {
                throw new BusinessRuleViolation('Tu roadmap cambió mientras lo ordenabas. Volvé a intentarlo.', 'roadmap_changed', 409);
            }
            foreach ($reportIds as $index => $reportId) {
                RoadmapItem::query()->where('user_id', $user->id)->where('report_id', $reportId)->update(['position' => $index + 1]);
            }
        });
    }
}
