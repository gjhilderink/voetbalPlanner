<?php

declare(strict_types=1);

namespace App\Enums;

enum MemberRole: string
{
    case Player = 'player';
    case Coach = 'coach';

    public function label(): string
    {
        return match($this) {
            self::Player => 'Speler',
            self::Coach => 'Coach',
        };
    }
}
