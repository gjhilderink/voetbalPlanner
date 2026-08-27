<?php

declare(strict_types=1);

namespace App\Filament\Resources\TeamDocumentResource\Pages;

use App\Filament\Resources\TeamDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTeamDocument extends EditRecord
{
    protected static string $resource = TeamDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    /** Zie CreateTeamDocument: na het vervangen van het bestand opnieuw meten. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TeamDocumentResource::vulBestandsgegevens($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
