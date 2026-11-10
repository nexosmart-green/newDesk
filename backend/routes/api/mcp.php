<?php

use App\Http\Controllers\Api\V1\Mcp\AiConnectionController;
use Illuminate\Support\Facades\Route;

Route::get('/me/ai-tokens', [AiConnectionController::class, 'index']);
Route::post('/me/ai-tokens', [AiConnectionController::class, 'store']);
Route::delete('/ai-tokens/{token}', [AiConnectionController::class, 'destroy']);
Route::get('/ai-tokens', [AiConnectionController::class, 'all']);
