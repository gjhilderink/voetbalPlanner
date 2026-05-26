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
        return new self(
            externalId: (string) $data['id'],
            name: $data['name'] ?? $data['naam'] ?? '',
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? $data['telefoon'] ?? null,
            dateOfBirth: $data['date_of_birth'] ?? $data['geboortedatum'] ?? null,
            role: $data['role'] ?? $data['rol'] ?? 'player',
            isActive: (bool) ($data['active'] ?? $data['actief'] ?? true),
        );
    }
}
