<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Een eigen vlag voor "de verzameltijd is met de hand gezet".
 *
 * Tot nu toe was `sportlink_arrival_time` zelf het teken: stond daar een tijd
 * in, dan liet de synchronisatie arrival_time met rust. Dat werkt alleen als de
 * bond óók een verzameltijd doorgaf - want alleen dan valt er iets te bewaren.
 *
 * Geeft Sportlink niets door, dan bleef die kolom leeg, ook nadat een coach een
 * tijd had ingevuld. De eerstvolgende ronde schreef arrival_time daarna gewoon
 * weer op null en de ingevulde tijd was weg. Precies het geval dat het vaakst
 * voorkomt: je vult een verzameltijd in juist omdat de bond er geen heeft.
 *
 * Met een aparte vlag staat "is met de hand gezet" los van "wat zei de bond",
 * en die twee vragen zijn ook echt los van elkaar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->boolean('arrival_time_custom')
                ->default(false)
                ->after('sportlink_arrival_time');
        });

        // Wedstrijden die al een bewaarde bondstijd hebben, waren onder de oude
        // regel met de hand gezet. Zonder deze stap zou de synchronisatie ze na
        // deze migratie alsnog overschrijven.
        DB::table('matches')
            ->whereNotNull('sportlink_arrival_time')
            ->update(['arrival_time_custom' => true]);
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('arrival_time_custom');
        });
    }
};
