<?php

use App\Http\Controllers\Api\V1\Tasks\TaskAttachmentController;
use App\Http\Controllers\Api\V1\Tasks\TaskCategoryController;
use App\Http\Controllers\Api\V1\Tasks\TaskChecklistController;
use App\Http\Controllers\Api\V1\Tasks\TaskCommentController;
use App\Http\Controllers\Api\V1\Tasks\TaskController;
use App\Http\Controllers\Api\V1\Tasks\TaskImageController;
use App\Http\Controllers\Api\V1\Tasks\TaskLabelController;
use App\Http\Controllers\Api\V1\Tasks\TaskStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/task-statuses', [TaskStatusController::class, 'index']);
Route::post('/task-statuses', [TaskStatusController::class, 'store']);
Route::put('/task-statuses/order', [TaskStatusController::class, 'reorder']);
Route::put('/task-statuses/{taskStatus}', [TaskStatusController::class, 'update']);
Route::delete('/task-statuses/{taskStatus}', [TaskStatusController::class, 'destroy']);

// Module switch per role (Configuración → Roles); the Admin always has every module.
Route::middleware('module:projects')->group(function () {
    Route::get('/me/tasks', [TaskController::class, 'mine']);
    Route::get('/tasks', [TaskController::class, 'all']);
    Route::get('/projects/{project}/tasks', [TaskController::class, 'index']);
    Route::post('/projects/{project}/tasks', [TaskController::class, 'store']);
    // {task} accepts the numeric id or the code (`bhn-60`).
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::put('/tasks/{task}/status', [TaskController::class, 'updateStatus']);
    Route::put('/tasks/{task}/assignees', [TaskController::class, 'assign']);
    Route::put('/tasks/{task}/project', [TaskController::class, 'move']);
    Route::get('/tasks/{task}/activity', [TaskController::class, 'activity']);
    Route::get('/tasks/{task}/comments', [TaskCommentController::class, 'index']);
    Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store']);

    Route::get('/task-categories', [TaskCategoryController::class, 'index']);
    Route::post('/task-categories', [TaskCategoryController::class, 'store']);
    Route::put('/task-categories/{taskCategory}', [TaskCategoryController::class, 'update']);
    Route::delete('/task-categories/{taskCategory}', [TaskCategoryController::class, 'destroy']);
    Route::get('/task-labels', [TaskLabelController::class, 'index']);
    Route::post('/task-labels', [TaskLabelController::class, 'store']);
    Route::put('/task-labels/{taskLabel}', [TaskLabelController::class, 'update']);
    Route::delete('/task-labels/{taskLabel}', [TaskLabelController::class, 'destroy']);

    Route::post('/projects/{project}/task-images', [TaskImageController::class, 'store']);
    Route::get('/task-images/{taskImage:uuid}', [TaskImageController::class, 'show']);

    // RF-04.08: checklist ("subtareas").
    Route::post('/tasks/{task}/checklist', [TaskChecklistController::class, 'store']);
    Route::put('/tasks/{task}/checklist/order', [TaskChecklistController::class, 'reorder']);
    Route::put('/checklist-items/{checklistItem}', [TaskChecklistController::class, 'update']);
    Route::delete('/checklist-items/{checklistItem}', [TaskChecklistController::class, 'destroy']);

    // RF-04.09: attachments.
    Route::get('/tasks/{task}/attachments', [TaskAttachmentController::class, 'index']);
    Route::post('/tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])->middleware('throttle:task-attachments');
    Route::get('/task-attachments/{taskAttachment:uuid}', [TaskAttachmentController::class, 'show']);
    Route::delete('/task-attachments/{taskAttachment:uuid}', [TaskAttachmentController::class, 'destroy']);
});
