<?php

use App\Http\Controllers\Api\V1\TimeTracking\CreateAndStartController;
use App\Http\Controllers\Api\V1\TimeTracking\DayClockController;
use App\Http\Controllers\Api\V1\TimeTracking\HourExtensionController;
use App\Http\Controllers\Api\V1\TimeTracking\ProjectHoursController;
use App\Http\Controllers\Api\V1\TimeTracking\TimeEntryController;
use App\Http\Controllers\Api\V1\TimeTracking\TimerController;
use Illuminate\Support\Facades\Route;

// Module switch per role (Configuración → Roles); the Admin always has every module.
Route::middleware('module:time')->group(function () {
    Route::get('/me/timer', [TimerController::class, 'show']);
    Route::post('/me/timer/stop', [TimerController::class, 'stop']);
    Route::post('/me/day-clock', [DayClockController::class, 'start']);
    Route::post('/me/day-clock/stop', [DayClockController::class, 'stop']);
    Route::post('/me/day-clocks/{dayClock}/split', [DayClockController::class, 'split']);
    Route::post('/tasks/{task}/timer', [TimerController::class, 'start']);
    Route::post('/projects/{project}/tasks/start', CreateAndStartController::class);
    Route::get('/me/timesheet', [TimeEntryController::class, 'timesheet']);
    Route::get('/time-entries', [TimeEntryController::class, 'index']);
    Route::get('/time-entries/days', [TimeEntryController::class, 'days']);
    Route::post('/tasks/{task}/time-entries', [TimeEntryController::class, 'store']);
    Route::put('/time-entries/{timeEntry}', [TimeEntryController::class, 'update']);
    Route::delete('/time-entries/{timeEntry}', [TimeEntryController::class, 'destroy']);
    Route::put('/time-entries/{timeEntry}/comment', [TimeEntryController::class, 'completeComment']);
    Route::get('/projects/{project}/hours', [ProjectHoursController::class, 'summary']);
    Route::get('/projects/{project}/time-entries', [ProjectHoursController::class, 'entries']);
    Route::post('/projects/{project}/allocations', [ProjectHoursController::class, 'allocate']);
    Route::get('/extension-requests', [HourExtensionController::class, 'index']);
    Route::post('/projects/{project}/extension-requests', [HourExtensionController::class, 'store']);
    Route::post('/extension-requests/{extensionRequest}/approve', [HourExtensionController::class, 'approve']);
    Route::post('/extension-requests/{extensionRequest}/reject', [HourExtensionController::class, 'reject']);
});
