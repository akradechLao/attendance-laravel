<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/employee', function () {
    return view('app');
});

Route::get('/login', function () {
    return view('app');
});

Route::get('/dashboard', function () {
    return view('app');
});

Route::get('/employees', function () {
    return view('app');
});

Route::get('/reports', function () {
    return view('app');
});

Route::get('/settings', function () {
    return view('app');
});
