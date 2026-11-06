<?php

use App\Http\Controllers\Api\V1\People\CatalogController;
use App\Http\Controllers\Api\V1\People\HourlyRateController;
use App\Http\Controllers\Api\V1\People\MyProfileController;
use App\Http\Controllers\Api\V1\People\PeopleController;
use Illuminate\Support\Facades\Route;

Route::get('/me/profile', [MyProfileController::class, 'show']);
Route::put('/me/profile', [MyProfileController::class, 'update']);
Route::post('/me/avatar', [MyProfileController::class, 'avatar']);
Route::put('/me/skills', [MyProfileController::class, 'skills']);
Route::get('/me/team', [MyProfileController::class, 'team']);

Route::get('/people', [PeopleController::class, 'index']);
Route::get('/people/{user}', [PeopleController::class, 'show']);
Route::put('/people/{user}', [PeopleController::class, 'update']);
Route::post('/people/{user}/deactivate', [PeopleController::class, 'deactivate']);
Route::post('/people/{user}/reactivate', [PeopleController::class, 'reactivate']);
Route::get('/people/{user}/rates', [HourlyRateController::class, 'index']);
Route::post('/people/{user}/rates', [HourlyRateController::class, 'store']);

Route::get('/catalogs/{catalog}', [CatalogController::class, 'index']);
Route::post('/catalogs/{catalog}', [CatalogController::class, 'store']);
Route::put('/catalogs/{catalog}/{item}', [CatalogController::class, 'update'])->whereNumber('item');
Route::delete('/catalogs/{catalog}/{item}', [CatalogController::class, 'destroy'])->whereNumber('item');
