<?php

use App\Http\Controllers\Api\V1\Clients\ClientController;
use Illuminate\Support\Facades\Route;

// Module switch per role (Configuración → Roles); the Admin always has every module.
Route::middleware('module:clients')->group(function () {
    Route::apiResource('clients', ClientController::class);
    Route::put('/clients/{client}/status', [ClientController::class, 'updateStatus']);
});
