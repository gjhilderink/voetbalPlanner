<?php

declare(strict_types=1);

namespace App\Filament\Resources\NewsItemResource\Pages;

use App\Filament\Resources\NewsItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNewsItems extends ListRecords
{
    protected static string $resource = NewsItemResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
