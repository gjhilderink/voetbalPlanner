<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnboardingSlideResource\Pages;

use App\Filament\Resources\OnboardingSlideResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOnboardingSlide extends EditRecord
{
    protected static string $resource = OnboardingSlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
