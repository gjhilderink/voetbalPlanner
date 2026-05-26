<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class MatchDTO
{
    public function __construct(
        public string $externalId,
        public string $teamExternalId,
        public string $opponent,
        public string $matchDatetime,
        public ?string $location = null,
        public bool $isHome = true,
        public string $status = 'scheduled',
    ) {}

    public static function fromMcpData(array $data, string $type = 'schedule'): self
    {
        // Actual Sportlink MCP field names from get_schedule / get_results tools
        $teamcode   = (string) ($data['teamcode'] ?? $data['team_id'] ?? '');
        $thuisteam  = $data['thuisteam'] ?? $data['home_team'] ?? null;
        $uitteam    = $data['uitteam'] ?? $data['away_team'] ?? null;
        $eigenTeam  = $data['eigenteam'] ?? null;

        // Determine opponent and home/away based on which side our team is on
        $isHome = true;
        $opponent = '';
        if ($eigenTeam !== null) {
            $isHome   = ($eigenTeam === $thuisteam);
            $opponent = $isHome ? ($uitteam ?? '') : ($thuisteam ?? '');
        } elseif ($thuisteam !== null && $uitteam !== null) {
            $opponent = $uitteam; // default: assume we are home
        } else {
            $opponent = $data['opponent'] ?? $data['tegenstander'] ?? '';
            $isHome   = (bool) ($data['thuis'] ?? $data['is_home'] ?? true);
        }

        // Build datetime from separate datum + tijd fields if needed
        $datum = $data['datum'] ?? $data['date'] ?? $data['match_datetime'] ?? '';
        $tijd  = $data['tijd'] ?? $data['time'] ?? '';
        $datetime = $datum;
        if ($datum && $tijd && !str_contains($datum, ' ') && !str_contains($datum, 'T')) {
            $datetime = $datum . ' ' . $tijd;
        }

        $status = match($type) {
            'results'  => 'played',
            'schedule' => 'scheduled',
            default    => $data['status'] ?? 'scheduled',
        };

        return new self(
            externalId: (string) ($data['wedstrijdnummer'] ?? $data['id'] ?? uniqid()),
            teamExternalId: $teamcode,
            opponent: $opponent,
            matchDatetime: $datetime,
            location: $data['accommodatie'] ?? $data['locatie'] ?? $data['location'] ?? null,
            isHome: $isHome,
            status: $status,
        );
    }
}
