<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TeamDTO;
use App\Models\SyncLog;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

class TeamSyncService
{
    public function __construct(
        private readonly SportlinkMcpService $mcpService
    ) {}

    public function sync(): SyncLog
    {
        $log = SyncLog::create([
            'type' => 'teams',
            'status' => 'started',
            'records_synced' => 0,
            'started_at' => now(),
        ]);

        try {
            $teamsData = $this->mcpService->getTeams();
            $synced = 0;

            foreach ($teamsData as $teamData) {
                $dto = TeamDTO::fromMcpData($teamData);
                $this->upsertTeam($dto);
                $synced++;
            }

            $log->update([
                'status' => 'completed',
                'records_synced' => $synced,
                'completed_at' => now(),
            ]);

            Log::info('Teams synced successfully', ['count' => $synced]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            Log::error('Team sync failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return $log;
    }

    private function upsertTeam(TeamDTO $dto): Team
    {
        return Team::updateOrCreate(
            ['external_id' => $dto->externalId],
            [
                'name' => $dto->name,
                'category' => $dto->category,
                'age_group' => $dto->ageGroup,
                'season' => $dto->season,
                'is_active' => $dto->isActive,
                'last_synced_at' => now(),
            ]
        );
    }
}
