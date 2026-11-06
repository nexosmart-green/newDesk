<?php

use App\Http\Controllers\Api\V1\Projects\ProjectController;
use App\Http\Controllers\Api\V1\Projects\ProjectMemberController;
use Illuminate\Support\Facades\Route;

// RF-03
Route::apiResource('projects', ProjectController::class);
Route::put('projects/{project}/members', [ProjectMemberController::class, 'update']);
