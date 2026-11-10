<?php

use App\Http\Controllers\Api\V1\Scheduling\CalendarController;
use App\Http\Controllers\Api\V1\Scheduling\ShiftController;
use Illuminate\Support\Facades\Route;

// Module switch per role (Configuración → Roles); the Admin always has every module.
Route::middleware('module:calendar')->group(function () {
    Route::get('/shift-templates', [ShiftController::class, 'templates']);
    Route::post('/shift-templates', [ShiftController::class, 'storeTemplate']);
    Route::put('/shift-templates/{shiftTemplate}', [ShiftController::class, 'updateTemplate']);
    Route::delete('/shift-templates/{shiftTemplate}', [ShiftController::class, 'destroyTemplate']);
    Route::get('/shifts', [ShiftController::class, 'grid']);
    Route::post('/shifts/assign', [ShiftController::class, 'assign']);
    Route::post('/shifts/copy-previous-week', [ShiftController::class, 'copyPreviousWeek']);
    Route::get('/me/calendar', [CalendarController::class, 'mine']);
    Route::get('/me/calendar-feed', [CalendarController::class, 'feedStatus']);
    Route::post('/me/calendar-feed', [CalendarController::class, 'issueFeed']);
    Route::get('/holidays', [CalendarController::class, 'holidays']);
    Route::put('/shifts/cells', [ShiftController::class, 'setCells']);
    Route::delete('/me/calendar-feed', [CalendarController::class, 'revokeFeed']);
    Route::post('/holidays', [CalendarController::class, 'storeHoliday']);
    Route::delete('/holidays/{holiday}', [CalendarController::class, 'destroyHoliday']);
});
