<?php

use App\Http\Controllers\Api\V1\Monitoring\ServerAgentController;
use App\Http\Controllers\Api\V1\Monitoring\ServerController;
use Illuminate\Support\Facades\Route;

// RF-13. monitoreo's /api/<path> lives at /api/v1/monitoring/<path> (ADR-0002 §10):
// its "staff" routes need monitoring.view, its "admin" ones monitoring.manage.
Route::prefix('monitoring')->group(function () {
    Route::middleware('can:monitoring.view')->group(function () {
        Route::get('servers', [ServerController::class, 'index']);
        Route::get('servers/{server}', [ServerController::class, 'show']);
        Route::post('servers/{server}/refresh', [ServerAgentController::class, 'refresh']);
        Route::post('servers/{server}/live', [ServerAgentController::class, 'live']);
        Route::get('servers/{server}/metrics', [ServerAgentController::class, 'metrics']);
    });

    Route::middleware('can:monitoring.manage')->group(function () {
        Route::post('servers', [ServerController::class, 'store']);
        Route::put('servers/{server}', [ServerController::class, 'update']);
        Route::delete('servers/{server}', [ServerController::class, 'destroy']);
        Route::post('servers/{server}/test', [ServerAgentController::class, 'test']);
    });
});
