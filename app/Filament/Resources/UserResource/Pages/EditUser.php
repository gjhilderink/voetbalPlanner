<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['managed_team_functions'] = $this->record->managedTeams()
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'team_id' => $t->id,
                'role'    => $t->pivot->role ?: 'coach',
            ])
            ->values()
            ->all();

        return $data;
    }

    protected function afterSave(): void
    {
        if (! array_key_exists('managed_team_functions', $this->data)) {
            return;
        }
        UserResource::syncManagedTeamFunctions($this->record, $this->data['managed_team_functions'] ?? []);
    }
}
