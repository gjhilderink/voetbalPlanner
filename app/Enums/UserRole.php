<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case ClubAdmin = 'club_admin';
    case Coach = 'coach';
    case Member = 'member';
    // Accounts die via een ouder/verzorger-koppeling zijn aangemaakt. Ze zijn
    // geen clublid; hun toegang loopt volledig via het gekoppelde kind.
    case Guardian = 'guardian';

    public function label(): string
    {
        return match($this) {
            self::SuperAdmin => 'Superbeheerder',
            self::ClubAdmin => 'Clubbeheerder',
            self::Coach => 'Coach',
            self::Member => 'Lid',
            self::Guardian => 'Ouder/verzorger',
        };
    }
}
