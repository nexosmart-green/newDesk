<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\TimeTracking\Enums\HourExtensionStatus;
use App\Domain\TimeTracking\Models\HourExtensionRequest;
use App\Domain\TimeTracking\Support\PendingExtensionLock;
use Illuminate\Support\Facades\DB;

/**
 * RF-05.08: approving = loading hours (AllocateHours) and closing the request, in one transaction.
 * Who may do it is HourExtensionRequestPolicy::approve.
 */
final class ApproveHourExtension
{
    public function __construct(
        private readonly PendingExtensionLock $pending,
        private readonly AllocateHours $allocateHours,
        private readonly RecordActivity $recordActivity,
    ) {}

    public function __invoke(User $actor, HourExtensionRequest $request, int $seconds, string $reason, ?string $reference = null): HourExtensionRequest
    {
        return DB::transaction(function () use ($actor, $request, $seconds, $reason, $reference) {
            $request = $this->pending->acquire($request);
            $allocation = ($this->allocateHours)($actor, $request->project, $seconds, $reason, $reference);

            $request->forceFill([
                'status' => HourExtensionStatus::Approved,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
                'allocation_id' => $allocation->id,
            ])->save();
            ($this->recordActivity)('hours.extension_approved', $request, $actor, after: ['seconds' => $seconds], projectId: $request->project_id);

            return $request;
        });
    }
}
