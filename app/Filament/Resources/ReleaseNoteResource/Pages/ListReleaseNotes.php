<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReleaseNoteResource\Pages;

use App\Filament\Resources\ReleaseNoteResource;
use Filament\Resources\Pages\ListRecords;

class ListReleaseNotes extends ListRecords
{
    protected static string $resource = ReleaseNoteResource::class;

    // Geen "Nieuw"-actie: release notes worden automatisch gegenereerd uit
    // features met status "Uitgebracht".
    protected function getHeaderActions(): array
    {
        return [];
    }
}
