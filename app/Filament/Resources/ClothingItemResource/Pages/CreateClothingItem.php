<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClothingItemResource\Pages;

use App\Filament\Resources\ClothingItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClothingItem extends CreateRecord
{
    protected static string $resource = ClothingItemResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
