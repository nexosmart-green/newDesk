<?php

use App\Http\Controllers\Api\V1\Tasks\TaskCommentController;
use App\Http\Controllers\Api\V1\Tasks\TaskController;
use App\Http\Controllers\Api\V1\Tasks\TaskStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/task-statuses', [TaskStatusController::class, 'index']);
Route::post('/task-statuses', [TaskStatusController::class, 'store']);
Route::put('/task-statuses/order', [TaskStatusController::class, 'reorder']);
Route::put('/task-statuses/{taskStatus}', [TaskStatusController::class, 'update']);
Route::delete('/task-statuses/{taskStatus}', [TaskStatusController::class, 'destroy']);

Route::get('/me/tasks', [TaskController::class, 'mine']);
Route::get('/projects/{project}/tasks', [TaskController::class, 'index']);
Route::post('/projects/{project}/tasks', [TaskController::class, 'store']);

// {task} accepts the numeric id or the code (`bhn-60`).
Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
Route::put('/tasks/{task}', [TaskController::class, 'update']);
Route::put('/tasks/{task}/status', [TaskController::class, 'updateStatus']);
Route::put('/tasks/{task}/assignees', [TaskController::class, 'assign']);
Route::put('/tasks/{task}/project', [TaskController::class, 'move']);
Route::get('/tasks/{task}/comments', [TaskCommentController::class, 'index']);
Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store']);
