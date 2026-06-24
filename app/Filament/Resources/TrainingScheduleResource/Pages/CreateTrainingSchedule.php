<?php

declare(strict_types=1);

namespace App\Filament\Resources\TrainingScheduleResource\Pages;

use App\Filament\Resources\TrainingScheduleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrainingSchedule extends CreateRecord
{
    protected static string $resource = TrainingScheduleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['club_id'] = filament()->getTenant()?->id ?? auth()->user()->club_id;
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
