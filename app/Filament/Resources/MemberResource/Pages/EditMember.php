<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['team_functions'] = $this->record->teams()
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'team_id' => $t->id,
                'role'    => $t->pivot->role ?: 'player',
            ])
            ->values()
            ->all();

        return $data;
    }

    protected function afterSave(): void
    {
        if (! array_key_exists('team_functions', $this->data)) {
            return;
        }
        MemberResource::syncTeamFunctions($this->record, $this->data['team_functions'] ?? []);
    }
}
