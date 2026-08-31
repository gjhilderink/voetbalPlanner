<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Toegangscontrole aan of uit, per club.
 *
 * Standaard uit: een club die geen entree heft heeft niets aan een menu-item
 * dat naar een leeg scherm wijst, en aan een QR in het profiel van elk lid al
 * helemaal niet.
 *
 * Wel meteen aan voor clubs die de module al gebruiken - die hebben codes
 * aangemaakt of een activiteit op "gratis voor leden" gezet. Zonder die
 * inhaalslag zou een werkende opzet na deze migratie ineens verdwenen zijn,
 * en dat is precies het soort verrassing waar je een halve avond aan kwijt bent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->boolean('access_enabled')->default(false)->after('is_active');
        });

        // Clubs met codes.
        DB::table('clubs')
            ->whereExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('access_codes')
                ->whereColumn('access_codes.club_id', 'clubs.id'))
            ->update(['access_enabled' => true]);

        // En clubs met een activiteit die gratis is voor leden.
        DB::table('clubs')
            ->whereExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('agenda_items')
                ->whereColumn('agenda_items.club_id', 'clubs.id')
                ->where('agenda_items.free_for_members', true))
            ->update(['access_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('access_enabled');
        });
    }
};
