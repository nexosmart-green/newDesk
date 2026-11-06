<?php

use App\Http\Controllers\Api\V1\Portal\ReportController;
use Illuminate\Support\Facades\Route;

// RF-13.08: "Reportar un problema" and its chat (monitoreo /api/portal/projects/{slug}/reports…),
// inside /api/v1/portal for client users only (routes/portal.php). Throttles: AppServiceProvider.
Route::get('projects/{slug}/reports', [ReportController::class, 'index']);
Route::post('projects/{slug}/reports', [ReportController::class, 'store'])->middleware('throttle:portal-report-attempts');
Route::get('projects/{slug}/reports/{id}', [ReportController::class, 'show'])->whereNumber('id');
Route::post('projects/{slug}/reports/{id}/messages', [ReportController::class, 'storeMessage'])->whereNumber('id')
    ->middleware('throttle:portal-report-messages');
Route::get('projects/{slug}/reports/{id}/screenshot', [ReportController::class, 'screenshot'])->whereNumber('id');
Route::get('projects/{slug}/reports/{id}/attachments/{attachment}', [ReportController::class, 'attachment'])->whereNumber(['id', 'attachment']);
