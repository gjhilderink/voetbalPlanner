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
        return new self(
            externalId: (string) $data['id'],
            name: $data['name'] ?? $data['naam'] ?? '',
            category: $data['category'] ?? $data['categorie'] ?? null,
            ageGroup: $data['age_group'] ?? $data['leeftijdsklasse'] ?? null,
            season: $data['season'] ?? $data['seizoen'] ?? null,
            isActive: (bool) ($data['active'] ?? $data['actief'] ?? true),
        );
    }
}
