<?php

declare(strict_types=1);

namespace App\Filament\Resources\MatchResource\Pages;

use App\Filament\Resources\MatchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMatch extends CreateRecord
{
    protected static string $resource = MatchResource::class;
}
