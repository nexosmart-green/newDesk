<?php

use App\Http\Controllers\Api\V1\Scheduling\CalendarController;
use Illuminate\Support\Facades\Route;

// RNF-05: the root never reveals what runs behind it.
Route::get('/', fn () => response()->json(['status' => 'ok']));

// RF-06.07: personal iCal feed, authenticated by its revocable token.
Route::get('/calendar/{token}.ics', [CalendarController::class, 'feed'])->middleware('throttle:60,1');
