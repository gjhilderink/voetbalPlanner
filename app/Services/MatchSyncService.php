<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\MatchDTO;
use App\Models\FootballMatch;
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
            $schedule = $this->mcpService->getSchedule();
            $results  = $this->mcpService->getResults();
            $synced   = 0;

            foreach ([['schedule', $schedule], ['results', $results]] as [$type, $matchesData]) {
                foreach ($matchesData as $matchData) {
                    $dto  = MatchDTO::fromMcpData($matchData, $type);
                    $team = Team::where('external_id', $dto->teamExternalId)->first();

                    if (!$team) {
                        Log::warning('Team not found for match', ['team_external_id' => $dto->teamExternalId]);
                        continue;
                    }

                    $this->upsertMatch($dto, $team->id);
                    $synced++;
                }
            }

            $log->update([
                'status' => 'completed',
                'records_synced' => $synced,
                'completed_at' => now(),
            ]);

            Log::info('Matches synced successfully', ['count' => $synced]);
        } catch (\Throwable $e) {
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

    private function upsertMatch(MatchDTO $dto, string $teamId): FootballMatch
    {
        return FootballMatch::updateOrCreate(
            ['external_id' => $dto->externalId],
            [
                'team_id'        => $teamId,
                'opponent'       => $dto->opponent,
                'match_datetime' => $dto->matchDatetime,
                'location'       => $dto->location,
                'is_home'        => $dto->isHome,
                'status'         => $dto->status,
                'score_home'     => $dto->scoreHome,
                'score_away'     => $dto->scoreAway,
                'arrival_time'   => $dto->arrivalTime,
                'last_synced_at' => now(),
            ]
        );
    }
}
