<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class MatchDTO
{
    // Bon Boys club relation code — used to determine home/away from API data
    private const BON_BOYS_CLUB_CODE = 'BBKR536';

    public function __construct(
        public string $externalId,
        public string $teamExternalId,
        public string $opponent,
        public string $matchDatetime,
        public ?string $location = null,
        public bool $isHome = true,
        public string $status = 'scheduled',
        public ?int $scoreHome = null,
        public ?int $scoreAway = null,
        public ?string $arrivalTime = null,
    ) {}

    public static function fromMcpData(array $data, string $type = 'schedule'): self
    {
        // wedstrijdcode is the unique match identifier (wedstrijdnummer is NOT unique)
        $externalId = (string) ($data['wedstrijdcode'] ?? $data['id'] ?? uniqid());

        // Determine if Bon Boys is home or away from the club relation code
        $thuisCode  = $data['thuisteamclubrelatiecode'] ?? '';
        $uitCode    = $data['uitteamclubrelatiecode'] ?? '';
        $thuisTeam  = $data['thuisteam'] ?? '';
        $uitTeam    = $data['uitteam'] ?? '';

        if ($thuisCode === self::BON_BOYS_CLUB_CODE) {
            $isHome         = true;
            $opponent       = $uitTeam;
            $teamExternalId = (string) ($data['thuisteamid'] ?? '');
        } elseif ($uitCode === self::BON_BOYS_CLUB_CODE) {
            $isHome         = false;
            $opponent       = $thuisTeam;
            $teamExternalId = (string) ($data['uitteamid'] ?? '');
        } else {
            // Fallback: use teamnaam to find our team (schedule has this field)
            $isHome         = true;
            $opponent       = $uitTeam ?: ($data['tegenstander'] ?? '');
            $teamExternalId = (string) ($data['thuisteamid'] ?? $data['teamcode'] ?? '');
        }

        // wedstrijddatum is a proper ISO datetime — use it directly
        $matchDatetime = $data['wedstrijddatum'] ?? $data['datum'] ?? '';

        // Map API status to internal status
        $status = match(true) {
            $type === 'results'                              => 'played',
            isset($data['status']) && str_contains($data['status'], 'Uitgespeeld') => 'played',
            isset($data['status']) && str_contains($data['status'], 'Afgelast')    => 'cancelled',
            default                                         => 'scheduled',
        };

        // Parse score from "3 - 5" format
        $scoreHome = null;
        $scoreAway = null;
        if (isset($data['uitslag']) && str_contains($data['uitslag'], ' - ')) {
            [$h, $a] = explode(' - ', $data['uitslag'], 2);
            $scoreHome = is_numeric(trim($h)) ? (int) trim($h) : null;
            $scoreAway = is_numeric(trim($a)) ? (int) trim($a) : null;
        }

        return new self(
            externalId: $externalId,
            teamExternalId: $teamExternalId,
            opponent: $opponent,
            matchDatetime: $matchDatetime,
            location: $data['accommodatie'] ?? null,
            isHome: $isHome,
            status: $status,
            scoreHome: $scoreHome,
            scoreAway: $scoreAway,
            arrivalTime: ($data['verzameltijd'] ?? '') ?: null,
        );
    }
}
