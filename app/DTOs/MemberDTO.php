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
        // relatiecode is the unique ID; null means privacy-protected ("Afgeschermd") — caller must skip
        $externalId = $data['relatiecode'] ?? null;

        // Build name from components; naam is "Achternaam, Voornaam" format — prefer separate fields
        $parts = array_filter([
            $data['voornaam'] ?? null,
            $data['tussenvoegsel'] ?? null,
            $data['achternaam'] ?? null,
        ]);
        $name = implode(' ', $parts) ?: ($data['naam'] ?? '');

        // Map Sportlink roles to internal roles
        $rol  = $data['rol'] ?? '';
        $role = match(true) {
            str_contains($rol, 'Teamspeler')    => 'player',
            str_contains($rol, 'Technische')    => 'coach',
            str_contains($rol, 'Medische')      => 'medical',
            default                             => 'staff',
        };

        return new self(
            externalId: (string) ($externalId ?? ''),
            name: $name,
            email: $data['email'] ?? $data['email2'] ?? null,
            phone: $data['mobiel'] ?? $data['telefoon'] ?? $data['telefoon2'] ?? null,
            dateOfBirth: null, // not provided by get_team_players
            role: $role,
            isActive: ($data['einddatum'] === null), // null einddatum = currently active
        );
    }
}
