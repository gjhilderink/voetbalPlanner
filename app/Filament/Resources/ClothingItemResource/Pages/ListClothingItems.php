<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClothingItemResource\Pages;

use App\Filament\Resources\ClothingItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClothingItems extends ListRecords
{
    protected static string $resource = ClothingItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Kledingstuk toevoegen'),
        ];
    }
}
