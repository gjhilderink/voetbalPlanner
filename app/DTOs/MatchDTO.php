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

    public static function fromMcpData(array $data): self
    {
        return new self(
            externalId: (string) $data['id'],
            teamExternalId: (string) $data['team_id'],
            opponent: $data['opponent'] ?? $data['tegenstander'] ?? '',
            matchDatetime: $data['match_datetime'] ?? $data['datum'] ?? '',
            location: $data['location'] ?? $data['locatie'] ?? null,
            isHome: (bool) ($data['is_home'] ?? $data['thuis'] ?? true),
            status: $data['status'] ?? 'scheduled',
        );
    }
}
