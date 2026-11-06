<?php

use App\Http\Controllers\Api\V1\Notifications\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/notifications', [NotificationController::class, 'index']);
Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
Route::get('/me/notification-preferences', [NotificationController::class, 'preferences']);
Route::put('/me/notification-preferences', [NotificationController::class, 'updatePreferences']);
