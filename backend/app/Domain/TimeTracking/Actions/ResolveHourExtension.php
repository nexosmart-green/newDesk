<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\TimeTracking\Models\HourExtensionRequest;
use Illuminate\Support\Facades\DB;

/**
 * RF-05.08: approving = loading hours (AllocateHours); rejecting just closes the request.
 */
final class ResolveHourExtension
{
    public function __construct(
        private readonly AllocateHours $allocateHours,
        private readonly RecordActivity $recordActivity,
    ) {}

    public function approve(User $actor, HourExtensionRequest $request, int $seconds, string $reason, ?string $reference = null): HourExtensionRequest
    {
        return DB::transaction(function () use ($actor, $request, $seconds, $reason, $reference) {
            $request = $this->lockPending($actor, $request);
            $allocation = ($this->allocateHours)($actor, $request->project, $seconds, $reason, $reference);

            $request->forceFill(['status' => 'approved', 'resolved_by' => $actor->id, 'resolved_at' => now(), 'allocation_id' => $allocation->id])->save();

            return $request;
        });
    }

    public function reject(User $actor, HourExtensionRequest $request): HourExtensionRequest
    {
        return DB::transaction(function () use ($actor, $request) {
            $request = $this->lockPending($actor, $request);
            $request->forceFill(['status' => 'rejected', 'resolved_by' => $actor->id, 'resolved_at' => now()])->save();
            ($this->recordActivity)('hours.extension_rejected', $request, $actor, projectId: $request->project_id);

            return $request;
        });
    }

    private function lockPending(User $actor, HourExtensionRequest $request): HourExtensionRequest
    {
        if (! $actor->hasPermission(Permission::HoursAllocate)) {
            throw new BusinessRuleViolation('Tu rol no tiene permiso para cargar horas.', 'cannot_allocate', 403);
        }

        $request = HourExtensionRequest::query()->forCompany($request->company_id)->lockForUpdate()->findOrFail($request->id);
        if (! $request->isPending()) {
            throw new BusinessRuleViolation('Ese pedido ya fue resuelto.', 'extension_already_resolved');
        }

        return $request;
    }
}
