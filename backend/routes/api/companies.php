<?php

use App\Http\Controllers\Api\V1\Companies\CompanyController;
use Illuminate\Support\Facades\Route;

Route::get('/company', [CompanyController::class, 'show']);
Route::put('/company', [CompanyController::class, 'update']);
Route::post('/company/logo', [CompanyController::class, 'updateLogo']);
