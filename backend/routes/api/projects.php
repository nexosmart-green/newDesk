<?php

use App\Http\Controllers\Api\V1\Projects\ProjectController;
use App\Http\Controllers\Api\V1\Projects\ProjectMemberController;
use Illuminate\Support\Facades\Route;

Route::apiResource('projects', ProjectController::class)->except('destroy');
Route::put('/projects/{project}/status', [ProjectController::class, 'updateStatus']);
Route::get('/projects/{project}/activity', [ProjectController::class, 'activity']);
Route::post('/projects/{project}/members', [ProjectMemberController::class, 'store']);
Route::delete('/projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy']);
