<?php

declare(strict_types=1);

namespace App\Filament\Resources\AgendaCategoryResource\Pages;

use App\Filament\Resources\AgendaCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAgendaCategories extends ListRecords
{
    protected static string $resource = AgendaCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
