<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnboardingSlideResource\Pages;

use App\Filament\Resources\OnboardingSlideResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOnboardingSlide extends CreateRecord
{
    protected static string $resource = OnboardingSlideResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
