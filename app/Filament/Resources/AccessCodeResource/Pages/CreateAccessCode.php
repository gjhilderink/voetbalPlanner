<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccessCodeResource\Pages;

use App\Filament\Resources\AccessCodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAccessCode extends CreateRecord
{
    protected static string $resource = AccessCodeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
