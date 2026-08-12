<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnboardingSlideResource\Pages;

use App\Filament\Resources\OnboardingSlideResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOnboardingSlides extends ListRecords
{
    protected static string $resource = OnboardingSlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
