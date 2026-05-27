<?php

use App\Models\Club;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (!auth()->check()) {
        return redirect('/admin/login');
    }

    $user = auth()->user();

    if ($user->hasRole('super_admin')) {
        $club = Club::where('is_active', true)->orderBy('name')->first();
    } else {
        $club = $user->club;
    }

    return $club
        ? redirect('/admin/' . $club->slug)
        : redirect('/admin/login');
});

Route::get('/dashboard', fn() => redirect('/'));

Route::redirect('/login', '/admin/login')->name('login');

// Impersonation routes (guarded by the package middleware)
Route::impersonate();
