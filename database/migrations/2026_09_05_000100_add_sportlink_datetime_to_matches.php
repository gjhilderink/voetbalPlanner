<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De aanvangstijd zoals Sportlink hem doorgaf.
 *
 * Een coach kan de aanvangstijd voortaan zelf zetten. Dat botst met de
 * synchronisatie, die match_datetime elke ronde overschrijft met wat de bond
 * zegt: zonder deze kolom was een aanpassing binnen het uur weer weg.
 *
 * Is deze kolom gevuld, dan heeft iemand de tijd met de hand gezet. De
 * synchronisatie laat match_datetime dan met rust en schrijft hier alleen nog
 * bij wat Sportlink inmiddels zegt. Zo blijft de afspraak binnen het team
 * staan, is te zien dát het afwijkt, en gaat de officiële tijd niet verloren -
 * daarmee is terugzetten één handeling.
 *
 * Leeg betekent: niemand heeft eraan gezeten, en de bond is leidend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dateTime('sportlink_datetime')->nullable()->after('match_datetime');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('sportlink_datetime');
        });
    }
};
