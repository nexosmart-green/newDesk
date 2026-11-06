<?php

use App\Http\Controllers\Api\V1\Reports\HoursReportController;
use Illuminate\Support\Facades\Route;

Route::get('/reports/hours', [HoursReportController::class, 'overview']);
Route::get('/reports/hours/projects/{project}', [HoursReportController::class, 'project']);
