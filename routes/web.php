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

// Magic link redirect: email link → deep link naar de app
Route::get('/magic/{token}', function (string $token) {
    $record = \App\Models\MagicLinkToken::where('token', $token)->first();

    if (!$record || !$record->isValid()) {
        return view('magic-redirect', ['expired' => true, 'deepLink' => '']);
    }

    $scheme   = env('MAGIC_LINK_APP_SCHEME', 'voetbalplanner');
    $deepLink = "{$scheme}://verify?token={$token}";

    return view('magic-redirect', ['expired' => false, 'deepLink' => $deepLink]);
})->where('token', '[a-zA-Z0-9]{64}');

// Impersonation routes (guarded by the package middleware)
Route::impersonate();
