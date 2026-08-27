<?php

declare(strict_types=1);

namespace App\Filament\Resources\TeamDocumentResource\Pages;

use App\Filament\Resources\TeamDocumentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateTeamDocument extends CreateRecord
{
    protected static string $resource = TeamDocumentResource::class;

    /**
     * Type en grootte vastleggen bij het opslaan.
     *
     * FileUpload levert alleen het pad en (via storeFileNamesIn) de
     * oorspronkelijke naam; de rest moeten we zelf van schijf halen. Dat gebeurt
     * hier en niet in de app, want daar is een bestandsgrootte niet meer op te
     * vragen zonder het hele document te downloaden.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TeamDocumentResource::vulBestandsgegevens($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
