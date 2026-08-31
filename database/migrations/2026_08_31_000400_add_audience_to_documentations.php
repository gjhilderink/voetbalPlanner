<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Voor wie is deze handleiding-sectie bedoeld?
 *
 * 'all'   - iedereen, en dat is wat bestaande secties krijgen.
 * 'staff' - alleen coaches, trainers, assistenten en leiders.
 *
 * Een string en geen boolean 'coach_only': er komt ooit een derde doelgroep
 * (ouders bijvoorbeeld), en dan is een tweede boolean erbij zetten een
 * onhandige manier om drie waarden op te schrijven.
 *
 * Dit verbergt uitleg, geen gegevens. Wie de API rechtstreeks aanroept ziet
 * alsnog niets gevoeligs - het gaat om instructies die voor een speler alleen
 * maar verwarrend zijn omdat de knoppen er bij hem niet staan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentations', function (Blueprint $table) {
            $table->string('audience', 16)->default('all')->after('category');
        });

        // Startpunt voor wat er al staat. "Het Platform" en "Koppelingen" gaan
        // volledig over het beheerportaal en de techniek erachter; voor een
        // speler is dat uitleg over schermen die hij nooit ziet. Alles onder
        // "De App" blijft voor iedereen.
        //
        // Eenmalig, en daarna is het aan de beheerder: de seeder raakt dit veld
        // bij bestaande secties niet meer aan.
        DB::table('documentations')
            ->whereIn('category', ['platform', 'koppelingen'])
            ->update(['audience' => 'staff']);
    }

    public function down(): void
    {
        Schema::table('documentations', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
    }
};
