<?php

use App\Http\Controllers\Api\V1\Portal\StatusPageController;
use Illuminate\Support\Facades\Route;

// RF-13.07: the client's status pages (monitoreo /api/portal/projects…), inside /api/v1/portal for
// client users only (routes/portal.php). Each project is resolved by slug within the user's client.
Route::get('projects', [StatusPageController::class, 'index']);
Route::get('projects/{slug}', [StatusPageController::class, 'show']);
Route::get('projects/{slug}/logo', [StatusPageController::class, 'logo']);
Route::get('projects/{slug}/response-times', [StatusPageController::class, 'responseTimes']);
