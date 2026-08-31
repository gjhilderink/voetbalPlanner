<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccessEntryResource\Pages;

use App\Filament\Resources\AccessEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListAccessEntries extends ListRecords
{
    protected static string $resource = AccessEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
