<?php

declare(strict_types=1);

namespace App\Filament\Resources\TeamDocumentResource\Pages;

use App\Filament\Resources\TeamDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTeamDocuments extends ListRecords
{
    protected static string $resource = TeamDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
