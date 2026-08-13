<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AgendaCategory;
use App\Models\Club;
use Illuminate\Database\Seeder;

/**
 * Zet per club de standaardcategorieën voor de verenigingsagenda klaar als de
 * club er nog geen heeft. Idempotent: clubs met eigen categorieën worden
 * ongemoeid gelaten — zelfde opzet als OnboardingSlidesSeeder.
 */
class AgendaCategoriesSeeder extends Seeder
{
    private array $defaults = [
        ['slug' => 'toernooi',       'name' => 'Toernooi',       'color' => '#2563eb', 'icon' => 'emoji_events'],
        ['slug' => 'jeugd',          'name' => 'Jeugd',          'color' => '#16a34a', 'icon' => 'child_care'],
        ['slug' => 'senioren',       'name' => 'Senioren',       'color' => '#0891b2', 'icon' => 'sports_soccer'],
        ['slug' => 'vrijwilligers',  'name' => 'Vrijwilligers',  'color' => '#ca8a04', 'icon' => 'volunteer_activism'],
        ['slug' => 'clubactiviteit', 'name' => 'Clubactiviteit', 'color' => '#7c3aed', 'icon' => 'groups'],
        ['slug' => 'feest',          'name' => 'Feest',          'color' => '#db2777', 'icon' => 'celebration'],
        ['slug' => 'training',       'name' => 'Training',       'color' => '#0d9488', 'icon' => 'fitness_center'],
        ['slug' => 'vereniging',     'name' => 'Vereniging',     'color' => '#475569', 'icon' => 'apartment'],
        ['slug' => 'overig',         'name' => 'Overig',         'color' => '#6b7280', 'icon' => 'more_horiz'],
    ];

    public function run(): void
    {
        Club::query()->each(function (Club $club) {
            if (AgendaCategory::where('club_id', $club->id)->exists()) {
                return; // eigen categorieën behouden
            }

            foreach ($this->defaults as $i => $category) {
                AgendaCategory::create([
                    'club_id'    => $club->id,
                    'name'       => $category['name'],
                    'slug'       => $category['slug'],
                    'color'      => $category['color'],
                    'icon'       => $category['icon'],
                    'sort_order' => $i,
                    'is_active'  => true,
                ]);
            }
        });
    }
}
