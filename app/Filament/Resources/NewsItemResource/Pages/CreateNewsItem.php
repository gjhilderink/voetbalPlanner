<?php

declare(strict_types=1);

namespace App\Filament\Resources\NewsItemResource\Pages;

use App\Filament\Resources\NewsItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsItem extends CreateRecord
{
    protected static string $resource = NewsItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = filament()->getTenant();
        $user   = auth()->user();
        $data['club_id']   ??= $tenant?->id ?? $user?->club_id;
        $data['author_id'] ??= $user?->id;
        return $data;
    }
}
