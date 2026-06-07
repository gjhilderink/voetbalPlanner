<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaffGroupResource\Pages;

use App\Filament\Resources\StaffGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStaffGroup extends EditRecord
{
    protected static string $resource = StaffGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn() => StaffGroupResource::canDelete($this->getRecord())),
        ];
    }
}
