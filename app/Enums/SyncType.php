<?php

declare(strict_types=1);

namespace App\Enums;

enum SyncType: string
{
    case Teams = 'teams';
    case Members = 'members';
    case Matches = 'matches';
    case Coaches = 'coaches';
}
