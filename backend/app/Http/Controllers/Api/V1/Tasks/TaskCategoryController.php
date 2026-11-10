<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Tasks\Actions\DeleteTaskCategory;
use App\Domain\Tasks\Actions\SaveTaskCategory;
use App\Domain\Tasks\Models\TaskCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\SaveTaskCategoryRequest;
use App\Http\Resources\TaskCategoryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Company catalog of task categories (desk parity). One category per task.
 */
class TaskCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::ok(TaskCategoryResource::collection(TaskCategory::query()->withCount('tasks')->orderBy('name')->get()));
    }

    public function store(SaveTaskCategoryRequest $request, SaveTaskCategory $save): JsonResponse
    {
        Gate::authorize('create', TaskCategory::class);

        $category = $save($request->user(), $request->categoryName());

        return ApiResponse::ok(TaskCategoryResource::make($category->loadCount('tasks')), "Categoría {$category->name} creada.", 201);
    }

    public function update(SaveTaskCategoryRequest $request, TaskCategory $taskCategory, SaveTaskCategory $save): JsonResponse
    {
        Gate::authorize('update', $taskCategory);

        $category = $save($request->user(), $request->categoryName(), $taskCategory);

        return ApiResponse::ok(TaskCategoryResource::make($category->loadCount('tasks')), 'Categoría actualizada.');
    }

    public function destroy(Request $request, TaskCategory $taskCategory, DeleteTaskCategory $delete): JsonResponse
    {
        Gate::authorize('delete', $taskCategory);

        $delete($request->user(), $taskCategory);

        return ApiResponse::ok(message: "Categoría {$taskCategory->name} borrada.");
    }
}
