<?php

declare(strict_types=1);

namespace App\Filament\Resources\LegalPageResource\Pages;

use App\Filament\Resources\LegalPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLegalPages extends ListRecords
{
    protected static string $resource = LegalPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn() => LegalPageResource::canCreate()),
        ];
    }
}
