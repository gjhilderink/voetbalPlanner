<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Bijwerken of aanmaken op external_id, mét de weggegooide rijen in beeld.
 *
 * updateOrCreate kijkt langs soft-deleted rijen heen, maar de unieke index op
 * external_id geldt ook voor die rijen. Een wedstrijd die in de portal is
 * verwijderd maar nog in Sportlink staat, liet de hele synchronisatie omvallen
 * met "Duplicate entry" — en daarmee stopte ook alles wat er nog achteraan kwam.
 *
 * Wat weg is, blijft weg. Een beheerder die een wedstrijd verwijdert en er zelf
 * een aanmaakt, doet dat met een reden; die keuze elke sync terugdraaien zou
 * betekenen dat de wedstrijd er twee keer staat. Wie hem terug wil, haalt hem
 * uit de prullenbak.
 */
trait SynchroniseertOpExternalId
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $velden
     * @return Model|null  null = deze rij is bewust verwijderd en wordt overgeslagen
     */
    private function upsertOpExternalId(
        string $modelClass,
        ?string $externalId,
        array $velden,
    ): ?Model {
        /** @var Model|null $bestaand */
        $bestaand = $externalId === null || $externalId === ''
            ? null
            : $modelClass::withTrashed()->where('external_id', $externalId)->first();

        if ($bestaand && method_exists($bestaand, 'trashed') && $bestaand->trashed()) {
            // Eén regel in het log: anders is het niet te verklaren waarom er
            // in Sportlink iets staat wat hier niet verschijnt.
            Log::info('Sync: overgeslagen, staat in de prullenbak', [
                'model'       => $modelClass,
                'external_id' => $externalId,
            ]);

            return null;
        }

        $rij = $bestaand ?? new $modelClass(['external_id' => $externalId]);
        $rij->fill($velden)->save();

        return $rij;
    }
}
