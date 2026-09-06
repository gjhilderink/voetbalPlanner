<?php

declare(strict_types=1);

namespace App\Filament\Resources\MatchResource\Pages;

use App\Filament\Resources\MatchResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMatch extends EditRecord
{
    protected static string $resource = MatchResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    /**
     * Een hier ingevulde verzameltijd blijft staan.
     *
     * De synchronisatie schrijft arrival_time elke ronde opnieuw met wat de bond
     * zegt - ook met niets, als de bond niets zegt. Wat hier werd ingevuld was
     * daarmee binnen het uur weg, zonder dat er iets van te zien was.
     *
     * Dezelfde vlag als de app zet, zodat beide kanten hetzelfde betekenen:
     * arrival_time_custom aan is "hier is over nagedacht, laat staan". Wordt het
     * veld leeggemaakt, dan gaat de vlag er weer af en neemt de bond het over.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! array_key_exists('arrival_time', $data)) {
            return $data;
        }

        $klok = fn ($tijd) => filled($tijd) ? substr((string) $tijd, 0, 5) : null;

        $oud    = $klok($this->record->arrival_time);
        $nieuw  = $klok($data['arrival_time']);

        if ($nieuw === $oud) {
            return $data;
        }

        if ($nieuw === null) {
            // Leeggemaakt: terug naar wat de bond doorgeeft.
            $data['arrival_time_custom']    = false;
            $data['sportlink_arrival_time'] = null;

            return $data;
        }

        $data['arrival_time_custom'] = true;

        // De eerste keer bewaren wat de bond zei, zodat terugzetten in de app
        // één handeling blijft. Alleen als daar een tijd stond.
        if ($this->record->external_id
            && $this->record->sportlink_arrival_time === null
            && $oud !== null) {
            $data['sportlink_arrival_time'] = $this->record->arrival_time;
        }

        return $data;
    }
}
