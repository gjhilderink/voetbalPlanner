<?php

declare(strict_types=1);

namespace App\Enums;

enum MatchStatus: string
{
    case Scheduled = 'scheduled';
    case Played = 'played';
    case Cancelled = 'cancelled';
    case Postponed = 'postponed';

    public function label(): string
    {
        return match($this) {
            self::Scheduled => 'Gepland',
            self::Played => 'Gespeeld',
            self::Cancelled => 'Geannuleerd',
            self::Postponed => 'Uitgesteld',
        };
    }
}
