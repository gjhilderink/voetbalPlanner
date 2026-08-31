<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccessCodeResource\Pages;

use App\Filament\Resources\AccessCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAccessCode extends EditRecord
{
    protected static string $resource = AccessCodeResource::class;

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
