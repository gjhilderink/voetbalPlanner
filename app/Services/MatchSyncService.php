<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\MatchDTO;
use App\Models\Match as GameMatch;
use App\Models\SyncLog;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

class MatchSyncService
{
    public function __construct(
        private readonly SportlinkMcpService $mcpService
    ) {}

    public function sync(): SyncLog
    {
        $log = SyncLog::create([
            'type' => 'matches',
            'status' => 'started',
            'records_synced' => 0,
            'started_at' => now(),
        ]);

        try {
            $matchesData = $this->mcpService->getMatches();
            $synced = 0;

            foreach ($matchesData as $matchData) {
                $dto = MatchDTO::fromMcpData($matchData);
                $team = Team::where('external_id', $dto->teamExternalId)->first();

                if (!$team) {
                    Log::warning('Team not found for match', ['team_external_id' => $dto->teamExternalId]);
                    continue;
                }

                $this->upsertMatch($dto, $team->id);
                $synced++;
            }

            $log->update([
                'status' => 'completed',
                'records_synced' => $synced,
                'completed_at' => now(),
            ]);

            Log::info('Matches synced successfully', ['count' => $synced]);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            Log::error('Match sync failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return $log;
    }

    private function upsertMatch(MatchDTO $dto, string $teamId): GameMatch
    {
        return GameMatch::updateOrCreate(
            ['external_id' => $dto->externalId],
            [
                'team_id' => $teamId,
                'opponent' => $dto->opponent,
                'match_datetime' => $dto->matchDatetime,
                'location' => $dto->location,
                'is_home' => $dto->isHome,
                'status' => $dto->status,
                'last_synced_at' => now(),
            ]
        );
    }
}
