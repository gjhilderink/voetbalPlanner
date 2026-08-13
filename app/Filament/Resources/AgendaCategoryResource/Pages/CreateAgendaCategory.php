<?php

declare(strict_types=1);

namespace App\Filament\Resources\AgendaCategoryResource\Pages;

use App\Filament\Resources\AgendaCategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateAgendaCategory extends CreateRecord
{
    protected static string $resource = AgendaCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Hidden-veld dekt het normale pad; deze fallback vangt het geval dat
        // er buiten een tenant-context wordt aangemaakt.
        $data['club_id'] ??= filament()->getTenant()?->id ?? auth()->user()?->club_id;
        $data['slug']    = Str::slug($data['slug'] ?? $data['name']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
