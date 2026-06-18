<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BugReportController;
use App\Http\Controllers\Api\GuardianController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\BarDutyController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\DocumentationController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\LineupController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\NewsItemController;
use App\Http\Controllers\Api\StaffGroupController;
use App\Http\Controllers\Api\SwapRequestController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

// Globale catch-all voor OPTIONS preflight — moet ALS EERSTE staan zodat
// browsers altijd 204 + CORS headers krijgen, zelfs voor non-existent
// routes of routes achter auth:sanctum.
Route::options('{any?}', fn() => response('', 204))->where('any', '.*');

Route::prefix('v1')->group(function () {
    // Auth
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/magic-link', [AuthController::class, 'sendMagicLink']);
    Route::post('/auth/verify-magic-link', [AuthController::class, 'verifyMagicLink']);

    // Guardian: ouder/verzorger self-registratie (publiek, throttled)
    Route::post('/guardian/self-register', [GuardianController::class, 'selfRegister'])
         ->middleware(['throttle:3,1']);

    // Health
    Route::get('/health', fn() => response()->json(['status' => 'ok', 'timestamp' => now()]));

    // Diagnose: laat zien welke routes Laravel heeft gecached.
    // Voer uit: GET /api/v1/diagnose/routes — geen auth nodig.
    Route::get('/diagnose/routes', function () {
        $routes = collect(\Route::getRoutes())
            ->map(fn($r) => [
                'methods' => $r->methods(),
                'uri'     => $r->uri(),
            ])
            ->filter(fn($r) => str_starts_with($r['uri'], 'api/v1/'))
            ->values();
        return response()->json([
            'bug_reports_route_exists' => $routes->contains(
                fn($r) => str_contains($r['uri'], 'bug-reports')
            ),
            'bug_reports_table_exists' => \Schema::hasTable('bug_reports'),
            'storage_link_exists'      => is_link(public_path('storage')),
            'routes_count'             => $routes->count(),
            'app_env'                  => config('app.env'),
            'app_debug'                => config('app.debug'),
        ]);
    });

    // Catch-all OPTIONS preflight zodat de browser nooit op een unmatched
    // OPTIONS request stuit. Stuurt 204 met de standaard CORS headers (toegevoegd
    // door HandleCorsApi middleware).
    Route::options('/{any?}', fn() => response('', 204))->where('any', '.*');
    Route::get('/sync/health', [SyncController::class, 'healthCheck']);

    // Debug (remove after troubleshooting)
    Route::post('/debug/post-echo', fn(\Illuminate\Http\Request $r) => response()->json([
        'body_all'      => $r->all(),
        'body_raw'      => $r->getContent(),
        'content_type'  => $r->header('Content-Type'),
        'auth_header'   => $r->header('Authorization'),
    ]));
    Route::get('/debug/echo', fn(\Illuminate\Http\Request $r) => response()->json([
        'auth_header'   => $r->header('Authorization'),
        'bearer_token'  => $r->bearerToken(),
        'query_token'   => $r->query('token'),
        'all_headers'   => collect($r->headers->all())->map(fn($v) => implode(', ', $v)),
        'all_query'     => $r->query(),
    ]));
    Route::get('/debug/token', fn(\Illuminate\Http\Request $r) => response()->json((function () use ($r) {
        $raw = $r->bearerToken();
        if (!$raw) return ['error' => 'no bearer token'];
        [$id, $hash] = array_pad(explode('|', $raw, 2), 2, null);
        $pat = \Laravel\Sanctum\PersonalAccessToken::find($id);
        if (!$pat) return ['error' => 'token not found in db', 'id' => $id];
        $user = \App\Models\User::withTrashed()->find($pat->tokenable_id);
        return [
            'token_id'       => $pat->id,
            'tokenable_type' => $pat->tokenable_type,
            'tokenable_id'   => $pat->tokenable_id,
            'hash_match'     => hash_equals($pat->token, hash('sha256', $hash ?? '')),
            'user_found'     => !!$user,
            'user_deleted'   => $user?->deleted_at ? true : false,
            'user_active'    => $user?->is_active,
        ];
    })()));

    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Documentation
        Route::get('/documentation', [DocumentationController::class, 'index']);

        // Branding
        Route::get('/branding', [BrandingController::class, 'show']);

        // Banners
        Route::get('/banners', [BannerController::class, 'index']);

        // Nieuws
        Route::get('/news', [NewsItemController::class, 'index']);
        Route::get('/news/{newsItem}', [NewsItemController::class, 'show']);

        // Teams
        Route::get('/teams', [TeamController::class, 'index']);
        Route::get('/teams/{team}', [TeamController::class, 'show']);
        Route::get('/teams/{team}/members', [TeamController::class, 'members']);

        // Members
        Route::get('/members', [MemberController::class, 'index']);
        Route::get('/members/{member}', [MemberController::class, 'show']);

        // Matches
        Route::get('/matches', [MatchController::class, 'index']);
        Route::get('/matches/{match}', [MatchController::class, 'show']);
        Route::patch('/matches/{match}', [MatchController::class, 'update']);

        // Lineups
        Route::get('/matches/{match}/lineup', [LineupController::class, 'show']);
        Route::post('/matches/{match}/lineup', [LineupController::class, 'store']);

        // Goals
        Route::get('/matches/{match}/goals', [GoalController::class, 'index']);
        Route::post('/matches/{match}/goals', [GoalController::class, 'store']);
        Route::delete('/matches/{match}/goals/{goal}', [GoalController::class, 'destroy']);

        // Bar duties
        Route::apiResource('bar-duties', BarDutyController::class)
            ->only(['index', 'show', 'store', 'update', 'destroy']);
        Route::patch('bar-duties/{barDuty}/members', [BarDutyController::class, 'assignMembers']);
        // POST (i.p.v. PATCH) omdat sommige shared hosts (Apache + mod_security2)
        // PATCH op nieuwe URLs blokkeren met een 405 HTML response.
        Route::post('bar-duties/{barDuty}/self-assign', [BarDutyController::class, 'selfAssign']);

        // Staff groups
        Route::apiResource('staff-groups', StaffGroupController::class)
            ->only(['index', 'show', 'store', 'update', 'destroy']);
        Route::patch('staff-groups/{staffGroup}/members', [StaffGroupController::class, 'syncMembers']);

        // Swap requests
        Route::get('swap-requests/incoming', [SwapRequestController::class, 'incoming']);
        Route::post('swap-requests', [SwapRequestController::class, 'store']);
        Route::patch('swap-requests/{swapRequest}/accept', [SwapRequestController::class, 'accept']);
        Route::patch('swap-requests/{swapRequest}/decline', [SwapRequestController::class, 'decline']);

        // Profiel
        Route::patch('/profile/photo', [ProfileController::class, 'updatePhoto']);

        // Bug reports (throttle: 5 per minute)
        Route::post('/bug-reports', [BugReportController::class, 'store'])
             ->middleware(['throttle:5,1']);

        // Guardian / ouder-verzorger koppelingen
        Route::prefix('guardian')->group(function () {
            // Lid maakt een ouder/verzorger-account aan (directe goedkeuring)
            Route::post('/create-parent-account', [GuardianController::class, 'createParentAccount'])
                 ->middleware(['throttle:5,1']);

            // Ouder: verzoek indienen voor extra kind (throttle: 5/min)
            Route::post('/request', [GuardianController::class, 'request'])
                 ->middleware(['throttle:5,1']);

            // Kind: openstaande verzoeken ophalen (ook bij login)
            Route::get('/pending', [GuardianController::class, 'pendingForMe']);

            // Kind: reageren op een verzoek
            Route::post('/{guardianLink}/respond', [GuardianController::class, 'respond']);

            // Kind / ouder / beheerder: koppeling intrekken
            Route::delete('/{guardianLink}/revoke', [GuardianController::class, 'revoke']);

            // Ouder: eigen gekoppelde kinderen ophalen
            Route::get('/children', [GuardianController::class, 'children']);

            // Ouder: overzicht van eigen ingediende verzoeken
            Route::get('/my-requests', [GuardianController::class, 'myRequests']);

            // Ouder: basisgegevens van een gekoppeld kind bekijken
            Route::get('/members/{member}/data', [GuardianController::class, 'childData']);
        });

        // Sync (admin only)
        Route::middleware('role:super_admin|club_admin')->prefix('sync')->group(function () {
            Route::get('/status', [SyncController::class, 'status']);
            Route::post('/all', [SyncController::class, 'syncAll']);
            Route::post('/teams', [SyncController::class, 'syncTeams']);
            Route::post('/members', [SyncController::class, 'syncMembers']);
            Route::post('/matches', [SyncController::class, 'syncMatches']);
        });
    });
});
