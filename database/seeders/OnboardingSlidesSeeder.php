<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Club;
use App\Models\OnboardingSlide;
use Illuminate\Database\Seeder;

/**
 * Zet per club de standaard-onboarding-slides klaar als de club er nog geen
 * heeft. Idempotent: clubs met bestaande slides worden ongemoeid gelaten, zodat
 * eigen aanpassingen behouden blijven.
 */
class OnboardingSlidesSeeder extends Seeder
{
    private array $defaults = [
        ['icon' => 'celebration',    'title' => 'Welkom bij VoetbalPlanner',
         'body' => 'Alles voor jouw team op één plek: wedstrijden, rijschema, bardienst en meer. Swipe verder voor een korte rondleiding.'],
        ['icon' => 'sports_soccer',  'title' => 'Wedstrijden altijd bij de hand',
         'body' => 'Bekijk je wedstrijden met datum en locatie en meld je met één tik af of aan. Je ziet ook wie er rijdt en wie fruit meeneemt.'],
        ['icon' => 'directions_car', 'title' => 'Rijschema & bardienst',
         'body' => 'Zie in één oogopslag wanneer jij moet rijden of achter de bar staat. Ruilen regel je met een wisselverzoek.'],
        ['icon' => 'chat',           'title' => 'Blijf in contact',
         'body' => 'Chat met je team, in groepen of één-op-één, en ontvang meldingen bij belangrijk nieuws.'],
        ['icon' => 'shield',         'title' => 'Extra voor coaches',
         'body' => 'Als coach stel je eenvoudig de opstelling, doelpunten, vlagger en gastspelers in — direct vanaf de wedstrijd.'],
    ];

    public function run(): void
    {
        Club::query()->each(function (Club $club) {
            if (OnboardingSlide::where('club_id', $club->id)->exists()) {
                return; // eigen slides behouden
            }
            foreach ($this->defaults as $i => $slide) {
                OnboardingSlide::create([
                    'club_id'    => $club->id,
                    'icon'       => $slide['icon'],
                    'title'      => $slide['title'],
                    'body'       => $slide['body'],
                    'sort_order' => $i,
                    'is_active'  => true,
                ]);
            }
        });
    }
}
