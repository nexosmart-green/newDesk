<?php

use Illuminate\Support\Facades\Route;

// RNF-05: the root never reveals what runs behind it.
Route::get('/', fn () => response()->json(['status' => 'ok']));
