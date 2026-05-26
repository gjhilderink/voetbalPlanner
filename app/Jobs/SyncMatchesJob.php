<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\MatchSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SyncMatchesJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $timeout = 180;

    public function handle(MatchSyncService $service): void
    {
        Log::info('SyncMatchesJob started');
        $service->sync();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncMatchesJob failed', ['error' => $exception->getMessage()]);
    }
}
