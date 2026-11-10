<?php

use App\Http\Controllers\Api\ErrorLogController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\V1\Auth\InvitationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorSetupController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Middleware\RejectMcpTokens;
use Illuminate\Support\Facades\Route;

// Observability (/bug-report contract) — outside auth.
Route::get('/health', [HealthController::class, 'index']);
Route::get('/errors', [ErrorLogController::class, 'index']);

Route::prefix('v1')->group(function () {
    // Guest auth flows.
    Route::post('/auth/login', [SessionController::class, 'store'])->middleware('throttle:login');
    Route::post('/auth/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:two-factor');

    Route::middleware('throttle:guest')->group(function () {
        Route::post('/auth/forgot-password', [PasswordResetController::class, 'forgot']);
        Route::post('/auth/reset-password', [PasswordResetController::class, 'reset']);
        Route::get('/invitations/{token}', [InvitationController::class, 'show']);
        Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept']);
    });

    // Client portal (RF-13.07): its own login and routes, closed to the team.
    Route::prefix('portal')->group(base_path('routes/portal.php'));

    Route::middleware(['auth:sanctum', 'active', RejectMcpTokens::class, 'throttle:api'])->group(function () {
        // Shared with the portal's client users; reachable before an Admin has enabled the mandatory 2FA.
        Route::post('/auth/logout', [SessionController::class, 'destroy']);
        Route::get('/me', [MeController::class, 'show']);

        // Team only from here on: client users live in the portal (CA-13.14).
        Route::middleware('team')->group(function () {
            Route::post('/me/two-factor', [TwoFactorSetupController::class, 'store']);
            Route::post('/me/two-factor/confirm', [TwoFactorSetupController::class, 'confirm']);
            Route::delete('/me/two-factor', [TwoFactorSetupController::class, 'destroy']);

            Route::middleware('two-factor')->group(function () {
                // One file per domain module: routes/api/<module>.php.
                foreach (glob(__DIR__.'/api/*.php') ?: [] as $moduleRoutes) {
                    require $moduleRoutes;
                }
            });
        });
    });
});
