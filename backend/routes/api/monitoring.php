<?php

use App\Http\Controllers\Api\V1\Monitoring\AlertController;
use App\Http\Controllers\Api\V1\Monitoring\DiscordController;
use App\Http\Controllers\Api\V1\Monitoring\IncidentNoteController;
use App\Http\Controllers\Api\V1\Monitoring\InventoryController;
use App\Http\Controllers\Api\V1\Monitoring\PortalClientController;
use App\Http\Controllers\Api\V1\Monitoring\PortalComponentController;
use App\Http\Controllers\Api\V1\Monitoring\PortalProjectController;
use App\Http\Controllers\Api\V1\Monitoring\ServerAgentController;
use App\Http\Controllers\Api\V1\Monitoring\ServerController;
use App\Http\Controllers\Api\V1\Monitoring\TeamUserController;
use Illuminate\Support\Facades\Route;

// RF-13. monitoreo's /api/<path> lives at /api/v1/monitoring/<path> (ADR-0002 §10):
// its "staff" routes need monitoring.view, its "admin" ones monitoring.manage.
Route::prefix('monitoring')->group(function () {
    Route::middleware('can:monitoring.view')->group(function () {
        Route::get('servers', [ServerController::class, 'index']);
        Route::get('servers/{server}', [ServerController::class, 'show']);
        Route::post('servers/{server}/refresh', [ServerAgentController::class, 'refresh']);
        Route::post('servers/{server}/live', [ServerAgentController::class, 'live']);
        Route::get('servers/{server}/metrics', [ServerAgentController::class, 'metrics']);
        Route::get('servers/{server}/sites', [ServerAgentController::class, 'sites']);
        Route::get('alerts', [AlertController::class, 'index']);
        Route::get('inventory/projects', [InventoryController::class, 'projects']);

        // Status page, the team's side (RF-13.06).
        Route::get('portal-admin/clients', [PortalClientController::class, 'index']);
        Route::get('portal-admin/clients/{client}', [PortalClientController::class, 'show']);
        Route::get('portal-admin/projects', [PortalProjectController::class, 'index']);
        Route::get('portal-admin/projects/{clientProject}', [PortalProjectController::class, 'show']);
        Route::get('portal-admin/projects/{clientProject}/logo', [PortalProjectController::class, 'logo']);
        Route::get('portal-admin/projects/{clientProject}/preview', [PortalProjectController::class, 'preview']);
        Route::get('portal-admin/projects/{clientProject}/response-times', [PortalProjectController::class, 'responseTimes']);
        Route::get('portal-admin/projects/{clientProject}/incidents', [PortalProjectController::class, 'incidents']);
        Route::get('portal-admin/projects/{clientProject}/suggestions', [PortalProjectController::class, 'suggestions']);
        Route::get('portal-admin/projects/{clientProject}/vulnerabilities', [PortalProjectController::class, 'vulnerabilities']);
        Route::get('portal-admin/folders', [InventoryController::class, 'folders']);
        Route::get('portal-admin/targets', [PortalComponentController::class, 'targets']);
        Route::get('discord/channels', [DiscordController::class, 'channels']);
    });

    Route::middleware('can:monitoring.manage')->group(function () {
        Route::post('servers', [ServerController::class, 'store']);
        Route::put('servers/{server}', [ServerController::class, 'update']);
        Route::delete('servers/{server}', [ServerController::class, 'destroy']);
        Route::post('servers/{server}/test', [ServerAgentController::class, 'test']);

        Route::get('users', [TeamUserController::class, 'index']);
        Route::put('users/{user}', [TeamUserController::class, 'update']);
        Route::get('discord/members', [DiscordController::class, 'members']);

        Route::post('portal-admin/clients/{client}/users', [PortalClientController::class, 'storeUser']);
        Route::put('portal-admin/clients/{client}/users/{clientUser}', [PortalClientController::class, 'updateUser']);
        Route::delete('portal-admin/clients/{client}/users/{clientUser}', [PortalClientController::class, 'destroyUser']);
        Route::put('portal-admin/projects/{clientProject}', [PortalProjectController::class, 'update']);
        Route::put('portal-admin/projects/{clientProject}/logo', [PortalProjectController::class, 'updateLogo']);
        Route::delete('portal-admin/projects/{clientProject}/logo', [PortalProjectController::class, 'destroyLogo']);
        Route::put('portal-admin/projects/{clientProject}/folders', [PortalProjectController::class, 'folders']);
        Route::post('portal-admin/projects/{clientProject}/discord-test', [DiscordController::class, 'test']);
        Route::post('portal-admin/projects/{clientProject}/components', [PortalComponentController::class, 'store']);
        Route::put('portal-admin/components/{component}', [PortalComponentController::class, 'update']);
        Route::delete('portal-admin/components/{component}', [PortalComponentController::class, 'destroy']);
        Route::post('portal-admin/components/{component}/check', [PortalComponentController::class, 'check']);
        Route::post('portal-admin/components/{component}/pagespeed', [PortalComponentController::class, 'pageSpeed']);
        Route::post('portal-admin/incidents/{period}/notes', [IncidentNoteController::class, 'store']);
        Route::delete('portal-admin/incident-notes/{note}', [IncidentNoteController::class, 'destroy']);
    });
});
