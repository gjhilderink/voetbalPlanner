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

            // Welke elftallen spelen er dit seizoen? Dat zijn de codes die in
            // een poule zitten. Sportlink noemt een elftal ook zonder poule -
            // teamsoort 'lokaal', competitiesoort '-' - en dat is een
            // registratie zonder competitie, geen spelend elftal. Precies zo
            // bleef Bon Boys 6 onder een oude teamcode rondhangen.
            //
            // De poule en niet de competitiesoort, en dus ook beker meegeteld.
            // Zou ik hier alleen 'regulier' nemen, dan zet één onverwachte
            // waarde in dat veld in één klap de halve club op niet-actief.
            $bekendeCodes = [];
            foreach ($teamsData as $teamData) {
                $code  = (string) ($teamData['teamcode'] ?? $teamData['id'] ?? '');
                $poule = trim((string) ($teamData['poulecode'] ?? ''));

                if ($code !== '' && $poule !== '') {
                    $bekendeCodes[$code] = true;
                }
            }

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

            $verouderd = $this->deactiveerVerdwenen(array_keys($bekendeCodes));

            $log->update([
                'status'         => 'completed',
                'records_synced' => $synced,
                'completed_at'   => now(),
            ]);

            Log::info('Teams synced successfully', [
                'count'        => $synced,
                'overgeslagen' => $overgeslagen,
                'verouderd'    => $verouderd,
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
     * Elftallen die Sportlink niet meer noemt, op niet-actief.
     *
     * Zo verdwijnen vorige jaargangen vanzelf uit de portal en uit de app.
     * Sportlink geeft de elftallen van dit seizoen; wat er niet meer bij staat
     * is voorbij. Dat is een betrouwbaarder maat dan het seizoenveld, want dat
     * is niet overal gevuld en niet overal hetzelfde opgeschreven.
     *
     * Op niet-actief en niet verwijderd: aan zo'n elftal hangen wedstrijden,
     * opstellingen en uitslagen van een heel seizoen. Die horen bewaard te
     * blijven, en één vinkje in de portal zet het terug.
     *
     * Twee grenzen. Een leeg antwoord verandert niets - dan weten we niets, en
     * dat is geen reden om alles uit te zetten. En elftallen zonder externe code
     * blijven ongemoeid: die zijn met de hand aangemaakt en komen sowieso niet
     * uit Sportlink.
     *
     * @param  array<int, string>  $codes
     */
    private function deactiveerVerdwenen(array $codes): int
    {
        if ($codes === [] || ! $this->clubId) {
            return 0;
        }

        return Team::query()
            ->where('club_id', $this->clubId)
            ->where('is_active', true)
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->whereNotIn('external_id', $codes)
            ->update(['is_active' => false]);
    }

    /**
     * Hoort dit elftal bij de reguliere competitie?
     *
     * Sportlink schrijft er 'regulier', 'beker' of letterlijk '-' neer. Dat
     * laatste is een lokale registratie zonder competitie; die hoort niet in
     * de portal en valt hier dus af.
     *
     * Een leeg veld telt wel als ja. Schrijft een andere club het anders op,
     * dan halen we liever een dubbele regel binnen dan in stilte de halve
     * vereniging weg te laten.
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
