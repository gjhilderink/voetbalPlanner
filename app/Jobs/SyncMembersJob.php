<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\MemberSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SyncMembersJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $timeout = 180;

    public function handle(MemberSyncService $service): void
    {
        Log::info('SyncMembersJob started');
        $service->sync();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncMembersJob failed', ['error' => $exception->getMessage()]);
    }
}
