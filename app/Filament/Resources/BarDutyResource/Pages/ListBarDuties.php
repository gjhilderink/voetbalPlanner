<?php

declare(strict_types=1);

namespace App\Filament\Resources\BarDutyResource\Pages;

use App\Filament\Resources\BarDutyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBarDuties extends ListRecords
{
    protected static string $resource = BarDutyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn() => BarDutyResource::canCreate()),
        ];
    }
}
