<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BarDutyController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\LineupController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Health
    Route::get('/health', fn() => response()->json(['status' => 'ok', 'timestamp' => now()]));
    Route::get('/sync/health', [SyncController::class, 'healthCheck']);

    // Debug (remove after troubleshooting)
    Route::get('/debug/echo', fn(\Illuminate\Http\Request $r) => response()->json([
        'auth_header' => $r->header('Authorization'),
        'bearer_token' => $r->bearerToken(),
    ]));

    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

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
