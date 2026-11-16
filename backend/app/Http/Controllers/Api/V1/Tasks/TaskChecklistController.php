<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Tasks\Actions\AddChecklistItem;
use App\Domain\Tasks\Actions\RemoveChecklistItem;
use App\Domain\Tasks\Actions\ReorderChecklist;
use App\Domain\Tasks\Actions\UpdateChecklistItem;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskChecklistItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\ReorderChecklistRequest;
use App\Http\Requests\Tasks\SaveChecklistItemRequest;
use App\Http\Resources\TaskChecklistItemResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * RF-04.08: task checklist ("subtareas"). Whoever may edit the task manages its checklist.
 */
class TaskChecklistController extends Controller
{
    public function store(SaveChecklistItemRequest $request, Task $task, AddChecklistItem $add): JsonResponse
    {
        $item = $add($request->user(), $task, (string) $request->text());

        return ApiResponse::ok(TaskChecklistItemResource::make($item->load('doneBy')), 'Subtarea agregada.', 201);
    }

    public function update(SaveChecklistItemRequest $request, TaskChecklistItem $checklistItem, UpdateChecklistItem $update): JsonResponse
    {
        $item = $update($request->user(), $checklistItem, $request->text(), $request->done());

        return ApiResponse::ok(TaskChecklistItemResource::make($item->load('doneBy')), 'Subtarea actualizada.');
    }

    public function destroy(Request $request, TaskChecklistItem $checklistItem, RemoveChecklistItem $remove): JsonResponse
    {
        Gate::authorize('delete', $checklistItem);

        $remove($request->user(), $checklistItem);

        return ApiResponse::ok(message: 'Subtarea borrada.');
    }

    public function reorder(ReorderChecklistRequest $request, Task $task, ReorderChecklist $reorder): JsonResponse
    {
        $items = $reorder($task, $request->orderedIds());

        return ApiResponse::ok(TaskChecklistItemResource::collection($items), 'Orden actualizado.');
    }
}
