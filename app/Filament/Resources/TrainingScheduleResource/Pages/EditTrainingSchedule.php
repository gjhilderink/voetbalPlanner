<?php

declare(strict_types=1);

namespace App\Filament\Resources\TrainingScheduleResource\Pages;

use App\Filament\Resources\TrainingScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrainingSchedule extends EditRecord
{
    protected static string $resource = TrainingScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn() => TrainingScheduleResource::canDelete($this->record)),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
