<?php

declare(strict_types=1);

namespace App\Filament\Resources\AgendaItemResource\Pages;

use App\Filament\Resources\AgendaItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAgendaItem extends EditRecord
{
    protected static string $resource = AgendaItemResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
