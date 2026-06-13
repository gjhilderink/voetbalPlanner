<?php

declare(strict_types=1);

namespace App\Filament\Resources\GuardianLinkResource\Pages;

use App\Filament\Resources\GuardianLinkResource;
use Filament\Resources\Pages\ListRecords;

class ListGuardianLinks extends ListRecords
{
    protected static string $resource = GuardianLinkResource::class;
}
