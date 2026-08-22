<?php

use App\Http\Controllers\ClubRequestController;
use App\Models\Club;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (!auth()->check()) {
        return view('landing');
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

Route::get('/privacy', function () {
    $page = \App\Models\LegalPage::where('slug', 'privacy')->first();

    return view('privacy', [
        'title'     => $page?->title ?? 'Privacyverklaring',
        'body'      => $page?->body ?? '<p>De privacyverklaring is nog niet ingesteld.</p>',
        'updatedAt' => $page?->updated_at,
    ]);
})->name('privacy');

Route::get('/aanmelden', [ClubRequestController::class, 'create'])->name('club-request.create');
Route::post('/aanmelden', [ClubRequestController::class, 'store'])->name('club-request.store');
Route::get('/aanmelden/bedankt', [ClubRequestController::class, 'success'])->name('club-request.success');

Route::get('/dashboard', fn() => redirect('/'));

Route::redirect('/login', '/admin/login')->name('login');

// Magic link redirect: email link → deep link naar de app
Route::get('/magic/{token}', function (string $token) {
    $record = \App\Models\MagicLinkToken::where('token', $token)->first();

    if (!$record || !$record->isValid()) {
        return view('magic-redirect', ['expired' => true, 'deepLink' => '']);
    }

    $scheme   = env('MAGIC_LINK_APP_SCHEME', 'voetbalplanner');
    // Android routes a custom-scheme deep link only when BOTH scheme and host
    // match the app's intent-filter (android:host="voetbalplanner.nubix.nl").
    // The old URL had an empty host (voetbalplanner:///verify) so Android never
    // opened the app; iOS matches the scheme only, which is why this was
    // Android-specific. Keep the host in sync with the FlutterFlow deep-link host.
    $host     = env('MAGIC_LINK_APP_HOST', 'voetbalplanner.nubix.nl');
    $deepLink = "{$scheme}://{$host}/verify?token={$token}";

    return view('magic-redirect', ['expired' => false, 'deepLink' => $deepLink]);
})->where('token', '[a-zA-Z0-9]{64}');

// Gedeelde wedstrijdlink: https → deep link naar de app.
//
// Waarom via een webpagina en niet de deeplink zelf in het bericht: WhatsApp
// maakt alleen http(s)-adressen klikbaar, een voetbalplanner://-adres blijft
// platte tekst. Deze pagina toont niets over de wedstrijd — alleen het id staat
// in de URL — en stuurt meteen door naar de app.
Route::get('/wedstrijd/{match}', function (string $match) {
    $scheme = env('MAGIC_LINK_APP_SCHEME', 'voetbalplanner');
    // Scheme én host moeten matchen met de intent-filter van de app, net als bij
    // de magic link hierboven. Het pad komt overeen met routePath van
    // WedstrijdDetailPage ('/wedstrijd'), die matchId als queryparameter leest.
    $host = env('MAGIC_LINK_APP_HOST', 'voetbalplanner.nubix.nl');

    return view('match-redirect', [
        'deepLink' => "{$scheme}://{$host}/wedstrijd?matchId={$match}",
    ]);
})->where('match', '[0-9a-fA-F-]{36}');

// Live wedstrijdverslag, publiek te volgen via een geheime link die de coach
// deelt. Geen auth: wie de link heeft mag meekijken, zolang de wedstrijd loopt.
// Het pollen krijgt een eigen IP-limiet — web-routes vallen buiten throttleApi.
Route::get('/live/{token}', [\App\Http\Controllers\LivePageController::class, 'show'])
    ->where('token', '[a-zA-Z0-9]{64}')
    ->name('live.show');
Route::get('/live/{token}/state', [\App\Http\Controllers\LivePageController::class, 'state'])
    ->where('token', '[a-zA-Z0-9]{64}')
    ->middleware('throttle:120,1')
    ->name('live.state');

// Impersonation routes (guarded by the package middleware)
Route::impersonate();
