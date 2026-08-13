<?php

declare(strict_types=1);

namespace App\Filament\Resources\AgendaCategoryResource\Pages;

use App\Filament\Resources\AgendaCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditAgendaCategory extends EditRecord
{
    protected static string $resource = AgendaCategoryResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['slug'] = Str::slug($data['slug'] ?? $data['name']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
