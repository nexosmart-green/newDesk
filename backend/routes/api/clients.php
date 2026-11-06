<?php

use App\Http\Controllers\Api\V1\Clients\ClientController;
use Illuminate\Support\Facades\Route;

// RF-02
Route::apiResource('clients', ClientController::class);
