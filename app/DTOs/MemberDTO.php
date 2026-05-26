<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class MemberDTO
{
    public function __construct(
        public string $externalId,
        public string $name,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $dateOfBirth = null,
        public string $role = 'player',
        public bool $isActive = true,
    ) {}

    public static function fromMcpData(array $data): self
    {
        // Actual Sportlink MCP field names from get_team_players tool
        $name = $data['naam'] ?? $data['volledigenaam'] ?? $data['name'] ?? '';
        if (empty($name)) {
            $parts = array_filter([
                $data['roepnaam'] ?? $data['voornaam'] ?? null,
                $data['tussenvoegsel'] ?? null,
                $data['achternaam'] ?? null,
            ]);
            $name = implode(' ', $parts);
        }

        return new self(
            externalId: (string) ($data['relatienummer'] ?? $data['spelernummer'] ?? $data['id'] ?? ''),
            name: $name,
            email: $data['emailadres'] ?? $data['email'] ?? null,
            phone: $data['telefoonnummer'] ?? $data['phone'] ?? $data['telefoon'] ?? null,
            dateOfBirth: $data['geboortedatum'] ?? $data['date_of_birth'] ?? null,
            role: $data['rol'] ?? $data['role'] ?? 'player',
            isActive: (bool) ($data['actief'] ?? $data['active'] ?? true),
        );
    }
}
