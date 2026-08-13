<?php

declare(strict_types=1);

namespace App\Filament\Resources\AgendaItemResource\Pages;

use App\Filament\Resources\AgendaItemResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateAgendaItem extends CreateRecord
{
    protected static string $resource = AgendaItemResource::class;

    /** Onthoudt de "Push-melding sturen"-keuze; send_push is geen DB-kolom. */
    protected bool $sendPush = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->sendPush = (bool) ($data['send_push'] ?? false);
        unset($data['send_push']);

        $tenant = filament()->getTenant();
        $user   = auth()->user();
        $data['club_id']    ??= $tenant?->id ?? $user?->club_id;
        $data['created_by'] ??= $user?->id;

        return $data;
    }

    /**
     * Push pas ná het aanmaken, zodat de doelgroep-relaties (elftallen, groepen)
     * al zijn weggeschreven — die bepalen naar wie de melding gaat.
     */
    protected function afterCreate(): void
    {
        if (! $this->sendPush || ! $this->record->is_published) {
            return;
        }

        $this->record->load(['teams', 'staffGroups']);

        AgendaItemResource::pushToAudience(
            $this->record,
            $this->record->title,
            Str::limit(trim(strip_tags((string) ($this->record->summary ?: $this->record->description))), 140),
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
