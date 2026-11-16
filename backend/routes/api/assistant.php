<?php

use App\Domain\Identity\Enums\Permission;
use App\Http\Controllers\Api\V1\Assistant\AssistantController;
use App\Http\Controllers\Api\V1\Assistant\AssistantFileController;
use App\Http\Controllers\Api\V1\Assistant\AssistantProposalController;
use App\Http\Controllers\Api\V1\Assistant\AssistantUsageController;
use Illuminate\Support\Facades\Route;

// RF-13: the AI assistant, only for roles with `assistant.use` (Admin by default).
Route::middleware('can:'.Permission::AssistantUse->value)->prefix('assistant')->group(function () {
    Route::get('/conversations', [AssistantController::class, 'conversations']);
    Route::get('/conversations/{conversation}/messages', [AssistantController::class, 'messages']);
    Route::post('/messages', [AssistantController::class, 'ask']);
    Route::get('/messages/{message}', [AssistantController::class, 'show']);
    Route::post('/proposals/{proposal}/confirm', [AssistantProposalController::class, 'confirm']);
    Route::post('/proposals/{proposal}/cancel', [AssistantProposalController::class, 'cancel']);
    Route::get('/files/{file:uuid}', [AssistantFileController::class, 'download']);
});

// RF-13.10: usage of the month for whoever configures the company.
Route::get('/assistant/usage', AssistantUsageController::class)->middleware('can:'.Permission::CompanyManage->value);
