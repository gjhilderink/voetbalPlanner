<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Speeldag en geslacht bij een elftal.
 *
 * De leeftijdscategorie stond er al (age_group) en de competitiesoort ook
 * (category). Wat ontbrak is waarop je in de praktijk zoekt: speelt dit elftal
 * op zaterdag of zondag, en is het een jongens-, meiden- of gemengd team. Met
 * tientallen elftallen is de lijst zonder die twee niet te doorzoeken.
 *
 * Tekstkolommen en geen enum: de waarden komen uit Sportlink, en die bepaalt
 * zelf hoe hij ze noemt. Een enum die daar niet op past laat de synchronisatie
 * omvallen in plaats van een onbekende waarde gewoon te bewaren.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('match_day', 30)->nullable()->after('age_group');
            $table->string('gender', 30)->nullable()->after('match_day');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['match_day', 'gender']);
        });
    }
};
