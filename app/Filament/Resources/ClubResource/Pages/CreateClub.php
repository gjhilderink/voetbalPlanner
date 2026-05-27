<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClubResource\Pages;

use App\Filament\Resources\ClubResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClub extends CreateRecord
{
    protected static string $resource = ClubResource::class;
}
