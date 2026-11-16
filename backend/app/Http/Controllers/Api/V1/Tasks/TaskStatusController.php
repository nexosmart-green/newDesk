<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Tasks\Actions\DeleteTaskStatus;
use App\Domain\Tasks\Actions\ReorderTaskStatuses;
use App\Domain\Tasks\Actions\SaveTaskStatus;
use App\Domain\Tasks\Models\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\ReorderTaskStatusesRequest;
use App\Http\Requests\Tasks\SaveTaskStatusRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * RF-04.03 / RF-11.04: everyone reads the statuses (board columns); `company.manage` configures them.
 */
class TaskStatusController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::ok(TaskStatus::query()->orderBy('position')->get()->map(fn (TaskStatus $status) => $this->present($status)));
    }

    public function store(SaveTaskStatusRequest $request, SaveTaskStatus $save): JsonResponse
    {
        $status = $save($request->user(), $request->statusData());

        return ApiResponse::ok($this->present($status), 'Estado creado.', 201);
    }

    public function update(SaveTaskStatusRequest $request, TaskStatus $taskStatus, SaveTaskStatus $save): JsonResponse
    {
        $status = $save($request->user(), $request->statusData(), $taskStatus);

        return ApiResponse::ok($this->present($status), 'Estado actualizado.');
    }

    public function destroy(Request $request, TaskStatus $taskStatus, DeleteTaskStatus $delete): JsonResponse
    {
        Gate::authorize(Permission::CompanyManage->value);

        $delete($request->user(), $taskStatus);

        return ApiResponse::ok(message: 'Estado borrado.');
    }

    public function reorder(ReorderTaskStatusesRequest $request, ReorderTaskStatuses $reorder): JsonResponse
    {
        $reorder($request->user(), $request->orderedIds());

        return $this->index();
    }

    /** @return array<string, mixed> */
    private function present(TaskStatus $status): array
    {
        return [...$status->only('id', 'name', 'color', 'position'), 'kind' => $status->kind->value];
    }
}
