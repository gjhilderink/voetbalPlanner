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
        );
    }
}
