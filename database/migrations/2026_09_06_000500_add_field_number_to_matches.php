<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Het veldnummer van de wedstrijd.
 *
 * De accommodatie stond er al als `location`, maar op een complex met zes
 * velden zegt die alleen wáár je moet zijn en niet wélk veld. Sportlink geeft
 * het mee; tot nu toe lieten we het vallen.
 *
 * Tekst en geen getal: het is niet altijd een cijfer. "Veld 3", "Kunstgras 2"
 * en "A" komen allemaal voor, en een integer zou daar stilzwijgend een 0 van
 * maken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('field_number', 60)->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('field_number');
        });
    }
};
