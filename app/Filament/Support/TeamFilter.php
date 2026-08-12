<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;

/**
 * Gedeelde team-opties voor tabelfilters in het admin-panel.
 *
 * Beperkt de keuzelijst tot de club (tenant) van de sessie en — voor wie geen
 * beheerder is — tot de elftallen die de gebruiker beheert. Zonder deze scope
 * toont een relationship-filter alle teams van álle clubs, terwijl de tabel
 * zelf wél gescoped is (zie getEloquentQuery van de resources).
 */
class TeamFilter
{
    /**
     * Past club- en beheerscope toe op een Team-query en sorteert op naam.
     * Bedoeld als modifyQueryUsing van een SelectFilter::relationship().
     */
    public static function scopeQuery(Builder $query): Builder
    {
        $tenant = filament()->getTenant();
        if ($tenant) {
            $query->where('teams.club_id', $tenant->id);
        }

        $user = auth()->user();
        if ($user && ! $user->isAdmin()) {
            $query->whereIn('teams.id', $user->managedTeamIds());
        }

        return $query->orderBy('teams.name');
    }

    /**
     * Dezelfde scope als losse opties-array, voor filters zonder relationship().
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return self::scopeQuery(Team::query())->pluck('teams.name', 'teams.id')->all();
    }
}
