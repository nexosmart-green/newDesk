<?php

use App\Http\Controllers\Api\V1\Portal\PortalLoginController;
use Illuminate\Support\Facades\Route;

// RF-13.07: the client portal. monitoreo's /api/portal/<path> lives at /api/v1/portal/<path> (ADR-0002 §10).
// Logout is the shared /api/v1/auth/logout; the current user is /api/v1/me (with `client`).

Route::post('auth/request-code', [PortalLoginController::class, 'requestCode'])->middleware('throttle:portal-code');
Route::post('auth/verify-code', [PortalLoginController::class, 'verifyCode'])->middleware('throttle:portal-verify');

// One file per portal module, e.g. routes/portal/status-page.php. Client users only (D-037, CA-13.14).
Route::middleware(['auth:sanctum', 'active', 'client'])->group(function () {
    foreach (glob(__DIR__.'/portal/*.php') ?: [] as $portalRoutes) {
        require $portalRoutes;
    }
});
