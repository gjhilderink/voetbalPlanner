<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ClothingItem;
use App\Models\ClothingSize;
use App\Models\Club;
use Illuminate\Database\Seeder;

/**
 * Zet per club de gebruikelijke kledingstukken met maten klaar.
 *
 * Idempotent: een club die er al heeft wordt overgeslagen, zodat de commissie
 * haar eigen indeling houdt — zelfde opzet als AgendaCategoriesSeeder.
 *
 * De maten zijn een startpunt, geen voorschrift. Wil een club schoenmaten voor
 * sokken, dan past ze dat in de portal aan; daar is het beheerscherm voor.
 */
class ClothingSeeder extends Seeder
{
    /** @var array<int, array{naam: string, maten: array<int, string>}> */
    private array $defaults = [
        ['naam' => 'Shirt',        'maten' => ['XS', 'S', 'M', 'L', 'XL']],
        ['naam' => 'Broek',        'maten' => ['XS', 'S', 'M', 'L', 'XL']],
        ['naam' => 'Sokken',       'maten' => ['XS', 'S', 'M', 'L', 'XL']],
        ['naam' => 'Trainingspak', 'maten' => ['XS', 'S', 'M', 'L', 'XL']],
        // Een tas heeft geen maat, maar wel een regel: zo kan een lid aangeven
        // dat hij er een nodig heeft in plaats van dat het vak leeg blijft.
        ['naam' => 'Tas',          'maten' => ['One size']],
    ];

    public function run(): void
    {
        Club::query()->each(function (Club $club): void {
            if (ClothingItem::where('club_id', $club->id)->exists()) {
                return; // eigen indeling behouden
            }

            foreach ($this->defaults as $volgorde => $stuk) {
                $item = ClothingItem::create([
                    'club_id'    => $club->id,
                    'name'       => $stuk['naam'],
                    'sort_order' => $volgorde,
                    'is_active'  => true,
                ]);

                foreach ($stuk['maten'] as $i => $maat) {
                    ClothingSize::create([
                        'clothing_item_id' => $item->id,
                        'label'            => $maat,
                        'sort_order'       => $i,
                    ]);
                }
            }
        });
    }
}
