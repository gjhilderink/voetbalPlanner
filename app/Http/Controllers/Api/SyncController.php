<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\FullSyncJob;
use App\Jobs\SyncMatchesJob;
use App\Jobs\SyncMembersJob;
use App\Jobs\SyncTeamsJob;
use App\Models\Setting;
use App\Models\SyncLog;
use App\Services\SportlinkMcpService;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function __construct(
        private readonly SportlinkMcpService $mcpService
    ) {}

    public function status(): JsonResponse
    {
        $lastSync = SyncLog::latest()->first();

        return response()->json([
            'success' => true,
            'data' => [
                'mcp_configured' => $this->mcpService->isConfigured(),
                'last_sync' => $lastSync ? [
                    'type' => $lastSync->type,
                    'status' => $lastSync->status,
                    'records_synced' => $lastSync->records_synced,
                    'started_at' => $lastSync->started_at,
                    'completed_at' => $lastSync->completed_at,
                ] : null,
                'last_full_sync' => Setting::get('last_sync_at'),
            ],
            'message' => '',
        ]);
    }

    public function healthCheck(): JsonResponse
    {
        $result = $this->mcpService->healthCheck();

        return response()->json([
            'success' => $result['connected'],
            'data' => $result,
            'message' => $result['message'],
        ], $result['connected'] ? 200 : 503);
    }

    public function syncAll(): JsonResponse
    {
        FullSyncJob::dispatch();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Volledige synchronisatie gestart.',
        ]);
    }

    public function syncTeams(): JsonResponse
    {
        SyncTeamsJob::dispatch();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Team synchronisatie gestart.',
        ]);
    }

    public function syncMembers(): JsonResponse
    {
        SyncMembersJob::dispatch();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Leden synchronisatie gestart.',
        ]);
    }

    public function syncMatches(): JsonResponse
    {
        SyncMatchesJob::dispatch();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Wedstrijd synchronisatie gestart.',
        ]);
    }
}
