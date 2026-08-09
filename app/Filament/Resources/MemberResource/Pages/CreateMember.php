<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    protected function afterCreate(): void
    {
        if (! array_key_exists('team_functions', $this->data)) {
            return;
        }
        MemberResource::syncTeamFunctions($this->record, $this->data['team_functions'] ?? []);
    }
}
