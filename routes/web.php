<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Impersonation routes (guarded by the package middleware)
Route::impersonate();
