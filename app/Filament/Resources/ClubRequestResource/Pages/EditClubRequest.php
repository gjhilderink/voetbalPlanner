<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClubRequestResource\Pages;

use App\Filament\Resources\ClubRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClubRequest extends EditRecord
{
    protected static string $resource = ClubRequestResource::class;

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
