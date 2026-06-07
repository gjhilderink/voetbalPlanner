<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaffGroupResource\Pages;

use App\Filament\Resources\StaffGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStaffGroups extends ListRecords
{
    protected static string $resource = StaffGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn() => StaffGroupResource::canCreate()),
        ];
    }
}
