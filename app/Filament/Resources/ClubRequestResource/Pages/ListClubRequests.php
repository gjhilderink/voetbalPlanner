<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClubRequestResource\Pages;

use App\Filament\Resources\ClubRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListClubRequests extends ListRecords
{
    protected static string $resource = ClubRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
