<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\TeamSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SyncTeamsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function handle(TeamSyncService $service): void
    {
        Log::info('SyncTeamsJob started');
        $service->sync();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncTeamsJob failed', ['error' => $exception->getMessage()]);
    }
}
