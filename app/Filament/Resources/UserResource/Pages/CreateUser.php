<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Vangnet naast de default op het formulier: wie geen super admin is, krijgt
     * altijd de club van de sessie. Zonder dit kon een nieuwe gebruiker clubloos
     * worden aangemaakt en zag die daarna nergens data.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        if (! $user?->hasRole('super_admin')) {
            $data['club_id'] = filament()->getTenant()?->id ?? $user?->club_id;
        }

        return $data;
    }

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
