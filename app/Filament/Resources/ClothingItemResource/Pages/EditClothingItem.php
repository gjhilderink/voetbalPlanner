<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClothingItemResource\Pages;

use App\Filament\Resources\ClothingItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClothingItem extends EditRecord
{
    protected static string $resource = ClothingItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
