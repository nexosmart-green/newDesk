<?php

use App\Http\Controllers\Api\V1\Reports\HoursReportController;
use Illuminate\Support\Facades\Route;

// Module switch per role (Configuración → Roles); the Admin always has every module.
Route::middleware('module:reports')->group(function () {
    Route::get('/reports/hours', [HoursReportController::class, 'overview']);
    Route::get('/reports/hours/projects/{project}', [HoursReportController::class, 'project']);
});
