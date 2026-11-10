<?php

use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;

// No module middleware: each block of the panel is gated by the viewer's own modules/permissions.
Route::get('/me/dashboard', [DashboardController::class, 'show']);
