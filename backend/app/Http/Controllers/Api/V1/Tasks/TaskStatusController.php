<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Tasks\Actions\DeleteTaskStatus;
use App\Domain\Tasks\Actions\ReorderTaskStatuses;
use App\Domain\Tasks\Actions\SaveTaskStatus;
use App\Domain\Tasks\Enums\StatusKind;
use App\Domain\Tasks\Models\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * RF-04.03 / RF-11.04: everyone reads the statuses (board columns); `company.manage` configures them.
 */
class TaskStatusController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::ok(TaskStatus::query()->orderBy('position')->get()->map(fn (TaskStatus $status) => $this->present($status)));
    }

    public function store(Request $request, SaveTaskStatus $save): JsonResponse
    {
        Gate::authorize(Permission::CompanyManage->value);

        $status = $save($request->user(), $this->validated($request));

        return ApiResponse::ok($this->present($status), 'Estado creado.', 201);
    }

    public function update(Request $request, TaskStatus $taskStatus, SaveTaskStatus $save): JsonResponse
    {
        Gate::authorize(Permission::CompanyManage->value);

        $status = $save($request->user(), $this->validated($request, $taskStatus->id), $taskStatus);

        return ApiResponse::ok($this->present($status), 'Estado actualizado.');
    }

    public function destroy(Request $request, TaskStatus $taskStatus, DeleteTaskStatus $delete): JsonResponse
    {
        Gate::authorize(Permission::CompanyManage->value);

        $delete($request->user(), $taskStatus);

        return ApiResponse::ok(message: 'Estado borrado.');
    }

    public function reorder(Request $request, ReorderTaskStatuses $reorder): JsonResponse
    {
        Gate::authorize(Permission::CompanyManage->value);
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'distinct']]);

        $reorder($request->user()->company_id, array_map('intval', $request->input('ids')));

        return $this->index();
    }

    /** @return array{name: string, color: string, kind: string} */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('task_statuses', 'name')->where('company_id', $request->user()->company_id)->ignore($ignoreId)],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'kind' => ['required', Rule::enum(StatusKind::class)],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(TaskStatus $status): array
    {
        return [...$status->only('id', 'name', 'color', 'position'), 'kind' => $status->kind->value];
    }
}
