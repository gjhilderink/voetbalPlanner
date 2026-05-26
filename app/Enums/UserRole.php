<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case ClubAdmin = 'club_admin';
    case Coach = 'coach';
    case Member = 'member';

    public function label(): string
    {
        return match($this) {
            self::SuperAdmin => 'Super Admin',
            self::ClubAdmin => 'Club Admin',
            self::Coach => 'Coach',
            self::Member => 'Lid',
        };
    }
}
