<?php

use App\Http\Controllers\Api\V1\Clients\ClientController;
use Illuminate\Support\Facades\Route;

Route::apiResource('clients', ClientController::class);
Route::put('/clients/{client}/status', [ClientController::class, 'updateStatus']);
