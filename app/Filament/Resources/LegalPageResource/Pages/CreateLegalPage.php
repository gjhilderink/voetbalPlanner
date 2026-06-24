<?php

declare(strict_types=1);

namespace App\Filament\Resources\LegalPageResource\Pages;

use App\Filament\Resources\LegalPageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLegalPage extends CreateRecord
{
    protected static string $resource = LegalPageResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
