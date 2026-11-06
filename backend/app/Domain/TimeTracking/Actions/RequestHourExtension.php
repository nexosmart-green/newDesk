<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\TimeTracking\Events\HourExtensionRequested;
use App\Domain\TimeTracking\Models\HourExtensionRequest;

/**
 * RF-05.08: one click from the block. A second click while pending returns the same request.
 */
final class RequestHourExtension
{
    public function __invoke(User $user, Project $project, ?string $message = null): HourExtensionRequest
    {
        $pending = HourExtensionRequest::query()->forCompany($project->company_id)
            ->where('project_id', $project->id)->where('requested_by', $user->id)->where('status', 'pending')->first();
        if ($pending !== null) {
            return $pending;
        }

        $request = HourExtensionRequest::query()->create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'requested_by' => $user->id,
            'message' => $message !== null ? trim($message) : null,
        ]);

        HourExtensionRequested::dispatch($request, $user);

        return $request;
    }
}
