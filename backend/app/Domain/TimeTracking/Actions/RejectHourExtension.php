<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\TimeTracking\Enums\HourExtensionStatus;
use App\Domain\TimeTracking\Models\HourExtensionRequest;
use App\Domain\TimeTracking\Support\PendingExtensionLock;
use Illuminate\Support\Facades\DB;

/**
 * RF-05.08: rejecting just closes the request. Who may do it is HourExtensionRequestPolicy::reject.
 */
final class RejectHourExtension
{
    public function __construct(
        private readonly PendingExtensionLock $pending,
        private readonly RecordActivity $recordActivity,
    ) {}

    public function __invoke(User $actor, HourExtensionRequest $request): HourExtensionRequest
    {
        return DB::transaction(function () use ($actor, $request) {
            $request = $this->pending->acquire($request);
            $request->forceFill(['status' => HourExtensionStatus::Rejected, 'resolved_by' => $actor->id, 'resolved_at' => now()])->save();
            ($this->recordActivity)('hours.extension_rejected', $request, $actor, projectId: $request->project_id);

            return $request;
        });
    }
}
