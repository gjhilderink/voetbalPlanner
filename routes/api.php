<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BarDutyController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\DocumentationController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\LineupController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\SwapRequestController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/magic-link', [AuthController::class, 'sendMagicLink']);
    Route::post('/auth/verify-magic-link', [AuthController::class, 'verifyMagicLink']);

    // Health
    Route::get('/health', fn() => response()->json(['status' => 'ok', 'timestamp' => now()]));
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

        // Swap requests
        Route::get('swap-requests/incoming', [SwapRequestController::class, 'incoming']);
        Route::post('swap-requests', [SwapRequestController::class, 'store']);
        Route::patch('swap-requests/{swapRequest}/accept', [SwapRequestController::class, 'accept']);
        Route::patch('swap-requests/{swapRequest}/decline', [SwapRequestController::class, 'decline']);

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
