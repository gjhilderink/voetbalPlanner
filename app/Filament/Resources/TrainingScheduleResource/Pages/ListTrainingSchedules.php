<?php

declare(strict_types=1);

namespace App\Filament\Resources\TrainingScheduleResource\Pages;

use App\Filament\Resources\TrainingScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrainingSchedules extends ListRecords
{
    protected static string $resource = TrainingScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn() => TrainingScheduleResource::canCreate()),
        ];
    }
}
