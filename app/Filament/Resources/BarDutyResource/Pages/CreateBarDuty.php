<?php

declare(strict_types=1);

namespace App\Filament\Resources\BarDutyResource\Pages;

use App\Filament\Resources\BarDutyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBarDuty extends CreateRecord
{
    protected static string $resource = BarDutyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['club_id'] = filament()->getTenant()?->id;
        return $data;
    }
}
