<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Setting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class FullSyncJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 600;

    public function handle(): void
    {
        Log::info('FullSyncJob started');

        SyncTeamsJob::dispatch();
        SyncMembersJob::dispatch();
        SyncMatchesJob::dispatch();

        Setting::set('last_sync_at', now()->toISOString(), 'mcp');
        Log::info('FullSyncJob dispatched all sync jobs');
    }
}
