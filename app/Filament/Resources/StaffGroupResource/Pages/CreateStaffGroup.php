<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaffGroupResource\Pages;

use App\Filament\Resources\StaffGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStaffGroup extends CreateRecord
{
    protected static string $resource = StaffGroupResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['club_id'] = filament()->getTenant()?->id ?? auth()->user()->club_id;
        return $data;
    }
}
