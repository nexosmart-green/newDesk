<?php

use App\Http\Controllers\Api\ErrorLogController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

// Observability (/bug-report contract) — outside auth.
Route::get('/health', [HealthController::class, 'index']);
Route::get('/errors', [ErrorLogController::class, 'index']);

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [SessionController::class, 'store'])->middleware('throttle:login');

    // Client portal (RF-13.07): its own login and routes, closed to the team.
    Route::prefix('portal')->group(base_path('routes/portal.php'));

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('/auth/logout', [SessionController::class, 'destroy']);
        Route::get('/me', [MeController::class, 'show']);

        // Module routes: one file per domain module, e.g. routes/api/projects.php.
        // Team only: client users live in the portal (CA-13.14).
        Route::middleware('team')->group(function () {
            foreach (glob(__DIR__.'/api/*.php') ?: [] as $moduleRoutes) {
                require $moduleRoutes;
            }
        });
    });
});
