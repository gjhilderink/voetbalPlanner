<?php

declare(strict_types=1);

namespace App\Filament\Resources\BarDutyResource\Pages;

use App\Filament\Resources\BarDutyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBarDuty extends EditRecord
{
    protected static string $resource = BarDutyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn() => BarDutyResource::canDelete($this->getRecord())),
        ];
    }
}
