<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        if (! array_key_exists('managed_team_functions', $this->data)) {
            return;
        }
        UserResource::syncManagedTeamFunctions($this->record, $this->data['managed_team_functions'] ?? []);
    }
}
