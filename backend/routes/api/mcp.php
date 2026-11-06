<?php

use App\Http\Controllers\Api\V1\Auth\PersonalTokenController;
use Illuminate\Support\Facades\Route;

// RF-12.02 (minimal, for the monitoreo MCP): each user's own personal tokens.
Route::middleware('can:mcp.use')->group(function () {
    Route::get('me/tokens', [PersonalTokenController::class, 'index']);
    Route::post('me/tokens', [PersonalTokenController::class, 'store']);
    Route::delete('me/tokens/{token}', [PersonalTokenController::class, 'destroy'])->whereNumber('token');
});
