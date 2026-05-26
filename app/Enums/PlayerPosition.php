<?php

declare(strict_types=1);

namespace App\Enums;

enum PlayerPosition: string
{
    case Keeper = 'keeper';
    case Defender = 'defender';
    case Midfielder = 'midfielder';
    case Forward = 'forward';

    public function label(): string
    {
        return match($this) {
            self::Keeper => 'Keeper',
            self::Defender => 'Verdediger',
            self::Midfielder => 'Middenvelder',
            self::Forward => 'Aanvaller',
        };
    }
}
