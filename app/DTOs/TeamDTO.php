<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class TeamDTO
{
    public function __construct(
        public string $externalId,
        public string $name,
        public ?string $category = null,
        public ?string $ageGroup = null,
        public ?string $season = null,
        public bool $isActive = true,
        public ?string $matchDay = null,
        public ?string $gender = null,
    ) {}

    public static function fromMcpData(array $data): self
    {
        // Actual Sportlink MCP field names from get_teams tool
        return new self(
            externalId: (string) ($data['teamcode'] ?? $data['id'] ?? ''),
            name: $data['teamnaam'] ?? $data['name'] ?? $data['naam'] ?? '',
            category: $data['competitiesoort'] ?? $data['category'] ?? $data['categorie'] ?? null,
            ageGroup: $data['leeftijdscategorie'] ?? $data['age_group'] ?? $data['leeftijdsklasse'] ?? null,
            season: $data['season'] ?? $data['seizoen'] ?? null,
            isActive: (bool) ($data['active'] ?? $data['actief'] ?? true),
            // Speeldag en geslacht komen uit Sportlink onder een naam die per
            // koppeling kan verschillen; vandaar de rij alternatieven. Staat er
            // geen van allen in, dan blijft het veld leeg en verandert er niets.
            // 'sportlink:teams' laat zien welke sleutels er werkelijk zijn.
            matchDay: self::eersteGevulde($data, ['speeldag', 'match_day', 'dag', 'speeldagsoort']),
            gender: self::eersteGevulde($data, ['geslacht', 'gender', 'sexe', 'teamgeslacht']),
        );
    }

    /**
     * De eerste sleutel die er is en niet leeg is.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $sleutels
     */
    private static function eersteGevulde(array $data, array $sleutels): ?string
    {
        foreach ($sleutels as $sleutel) {
            $waarde = $data[$sleutel] ?? null;

            if (is_scalar($waarde) && trim((string) $waarde) !== '') {
                return trim((string) $waarde);
            }
        }

        return null;
    }
}
