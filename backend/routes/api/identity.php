<?php

use App\Http\Controllers\Api\V1\Identity\RoleController;
use App\Http\Controllers\Api\V1\Identity\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/permissions', [RoleController::class, 'permissions']);
Route::apiResource('roles', RoleController::class)->except('show');

Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::put('/users/{user}/role', [UserController::class, 'updateRole']);
Route::post('/users/{user}/invitation', [UserController::class, 'resendInvitation']);
