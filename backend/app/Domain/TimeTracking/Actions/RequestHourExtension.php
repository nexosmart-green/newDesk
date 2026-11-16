<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\TimeTracking\Events\HourExtensionRequested;
use App\Domain\TimeTracking\Models\HourExtensionRequest;
use Illuminate\Support\Facades\DB;

/**
 * RF-05.08: one click from the block. A second click while pending returns the same request.
 */
final class RequestHourExtension
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $user, Project $project, ?string $message = null): HourExtensionRequest
    {
        $pending = HourExtensionRequest::query()->forCompany($project->company_id)->pending()
            ->where('project_id', $project->id)->where('requested_by', $user->id)->first();
        if ($pending !== null) {
            return $pending;
        }

        $request = DB::transaction(function () use ($user, $project, $message) {
            $request = HourExtensionRequest::query()->create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'requested_by' => $user->id,
                'message' => $message !== null ? trim($message) : null,
            ]);
            ($this->recordActivity)('hours.extension_requested', $request, $user, after: ['message' => $request->message], projectId: $project->id);

            return $request;
        });

        HourExtensionRequested::dispatch($request, $user);

        return $request;
    }
}
