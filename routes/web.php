<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// SPA - All routes return same blade view
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*');
