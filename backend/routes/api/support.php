<?php

use App\Http\Controllers\Api\V1\Monitoring\DailyReportController;
use App\Http\Controllers\Api\V1\Monitoring\ReportController;
use App\Http\Controllers\Api\V1\Monitoring\RoadmapController;
use Illuminate\Support\Facades\Route;

// RF-13.08, the team's side of the client problem reports: monitoreo's /api/portal-admin/reports and
// /api/portal-admin/roadmap at /api/v1/monitoring/… (ADR-0002 §10), "staff" = monitoring.view.
Route::prefix('monitoring/portal-admin')->middleware('can:monitoring.view')->group(function () {
    Route::get('reports', [ReportController::class, 'index']);
    Route::get('reports/attention', [ReportController::class, 'attention']);
    Route::get('reports/{report}', [ReportController::class, 'show'])->whereNumber('report');
    Route::put('reports/{report}', [ReportController::class, 'update'])->whereNumber('report');
    Route::get('reports/{report}/screenshot', [ReportController::class, 'screenshot'])->whereNumber('report');
    Route::post('reports/{report}/messages', [ReportController::class, 'storeMessage'])->whereNumber('report');
    Route::get('reports/{report}/attachments/{attachment}', [ReportController::class, 'attachment'])->whereNumber(['report', 'attachment']);

    Route::get('roadmap', [RoadmapController::class, 'index']);
    Route::post('roadmap', [RoadmapController::class, 'store']);
    Route::put('roadmap', [RoadmapController::class, 'reorder']);
    Route::delete('roadmap/{report}', [RoadmapController::class, 'destroy'])->whereNumber('report');
});

// "Generar reporte" (D-074), in Reportes and Mi roadmap: monitoreo's /api/daily-report.
Route::prefix('monitoring/daily-report')->middleware('can:monitoring.view')->group(function () {
    Route::get('status', [DailyReportController::class, 'status']);
    Route::post('/', [DailyReportController::class, 'store']);
});
