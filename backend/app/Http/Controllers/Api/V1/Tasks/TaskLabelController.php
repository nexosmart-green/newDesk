<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Tasks\Actions\DeleteTaskLabel;
use App\Domain\Tasks\Actions\SaveTaskLabel;
use App\Domain\Tasks\Models\TaskLabel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\SaveTaskLabelRequest;
use App\Http\Resources\TaskLabelResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Company catalog of colored task labels (desk parity). Any number per task.
 */
class TaskLabelController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::ok(TaskLabelResource::collection(TaskLabel::query()->withCount('tasks')->orderBy('name')->get()));
    }

    public function store(SaveTaskLabelRequest $request, SaveTaskLabel $save): JsonResponse
    {
        Gate::authorize('create', TaskLabel::class);

        $label = $save($request->user(), $request->labelName(), $request->string('color')->value());

        return ApiResponse::ok(TaskLabelResource::make($label->loadCount('tasks')), "Etiqueta {$label->name} creada.", 201);
    }

    public function update(SaveTaskLabelRequest $request, TaskLabel $taskLabel, SaveTaskLabel $save): JsonResponse
    {
        Gate::authorize('update', $taskLabel);

        $label = $save($request->user(), $request->labelName(), $request->string('color')->value(), $taskLabel);

        return ApiResponse::ok(TaskLabelResource::make($label->loadCount('tasks')), 'Etiqueta actualizada.');
    }

    public function destroy(Request $request, TaskLabel $taskLabel, DeleteTaskLabel $delete): JsonResponse
    {
        Gate::authorize('delete', $taskLabel);

        $delete($request->user(), $taskLabel);

        return ApiResponse::ok(message: "Etiqueta {$taskLabel->name} borrada.");
    }
}
