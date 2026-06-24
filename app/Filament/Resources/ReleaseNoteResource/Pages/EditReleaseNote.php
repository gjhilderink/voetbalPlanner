<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReleaseNoteResource\Pages;

use App\Filament\Resources\ReleaseNoteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReleaseNote extends EditRecord
{
    protected static string $resource = ReleaseNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn() => ReleaseNoteResource::canDelete($this->record)),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
