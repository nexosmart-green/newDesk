<?php

use App\Http\Controllers\Api\V1\Projects\ProjectController;
use App\Http\Controllers\Api\V1\Projects\ProjectMemberController;
use Illuminate\Support\Facades\Route;

// Module switch per role (Configuración → Roles); the Admin always has every module.
Route::middleware('module:projects')->group(function () {
    Route::apiResource('projects', ProjectController::class)->except('destroy');
    Route::put('/projects/{project}/status', [ProjectController::class, 'updateStatus']);
    Route::get('/projects/{project}/activity', [ProjectController::class, 'activity']);
    Route::post('/projects/{project}/members', [ProjectMemberController::class, 'store']);
    Route::delete('/projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy']);
});
