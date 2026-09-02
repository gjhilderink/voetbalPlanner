<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Concerns\SynchroniseertOpExternalId;
use App\DTOs\TeamDTO;
use App\Models\SyncLog;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

class TeamSyncService
{
    use SynchroniseertOpExternalId;

    private ?string $clubId = null;

    public function __construct(
        private readonly SportlinkMcpService $mcpService
    ) {}

    public function forClub(?string $clubId): static
    {
        $this->clubId = $clubId;
        $this->mcpService->forClub($clubId);
        return $this;
    }

    public function sync(): SyncLog
    {
        $log = SyncLog::create([
            'club_id'        => $this->clubId,
            'type'           => 'teams',
            'status'         => 'started',
            'records_synced' => 0,
            'started_at'     => now(),
        ]);

        try {
            $teamsData = $this->mcpService->getTeams();
            $synced = 0;

            // Deduplicate by teamcode — same team appears once per competition/poule
            $seen      = [];
            $overgeslagen = 0;

            foreach ($teamsData as $teamData) {
                // Alleen de reguliere competitie. Sportlink geeft hetzelfde
                // elftal ook terug onder beker en zaal, met een eigen teamcode -
                // en dan staat er twee keer "Bon Boys 3" in de portal zonder dat
                // te zien is welke welke is.
                //
                // Alleen overslaan als er echt een andere soort staat: is het
                // veld er niet, dan weten we niets en gooien we niets weg.
                if (! self::isRegulier($teamData)) {
                    $overgeslagen++;
                    continue;
                }

                $code = (string) ($teamData['teamcode'] ?? $teamData['id'] ?? '');
                if ($code === '' || isset($seen[$code])) {
                    continue;
                }
                $seen[$code] = true;
                $dto = TeamDTO::fromMcpData($teamData);
                $this->upsertTeam($dto);
                $synced++;
            }

            $log->update([
                'status'         => 'completed',
                'records_synced' => $synced,
                'completed_at'   => now(),
            ]);

            Log::info('Teams synced successfully', [
                'count'        => $synced,
                'overgeslagen' => $overgeslagen,
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
            Log::error('Team sync failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return $log;
    }

    /**
     * Hoort dit elftal bij de reguliere competitie?
     *
     * Onbekend telt als ja. Sportlink schrijft de soort niet overal hetzelfde
     * op, en een filter dat te streng is haalt in stilte de halve club weg -
     * veel erger dan een dubbele regel.
     *
     * @param  array<string, mixed>  $teamData
     */
    private static function isRegulier(array $teamData): bool
    {
        $soort = $teamData['competitiesoort']
            ?? $teamData['category']
            ?? $teamData['categorie']
            ?? null;

        if (! is_scalar($soort) || trim((string) $soort) === '') {
            return true;
        }

        return str_contains(mb_strtolower(trim((string) $soort)), 'regulier');
    }

    private function upsertTeam(TeamDTO $dto): ?Team
    {
        return $this->upsertOpExternalId(
            Team::class,
            $dto->externalId,
            [
                'club_id'        => $this->clubId,
                'name'           => $dto->name,
                'category'       => $dto->category,
                'age_group'      => $dto->ageGroup,
                'match_day'      => $dto->matchDay,
                'gender'         => $dto->gender,
                'season'         => $dto->season,
                'is_active'      => $dto->isActive,
                'last_synced_at' => now(),
            ]
        );
    }
}
