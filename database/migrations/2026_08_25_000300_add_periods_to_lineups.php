<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Een opstelling per speelperiode in plaats van één voor de hele wedstrijd.
 *
 * Daarmee vervalt het apart geplande wisselschema: het verschil tussen periode
 * 1 en 2 ís de wissel. Twee plekken die hetzelfde beweren lopen vroeg of laat
 * uiteen, dus de wissels worden voortaan afgeleid en niet meer opgeslagen.
 * lineup_substitutions is nooit in gebruik geweest en gaat er daarom weer af.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lineups', function (Blueprint $table) {
            // 2 helften of 4 kwarten; andere indelingen komen in de jeugd niet voor.
            $table->unsignedTinyInteger('periods')->default(2)->after('match_format');
        });

        Schema::table('lineup_players', function (Blueprint $table) {
            $table->unsignedTinyInteger('period')->default(1)->after('lineup_id');
            $table->index(['lineup_id', 'period']);
        });

        // Opruimen van een korte tussenstap waarin het wisselschema apart werd
        // opgeslagen. Met een guard: op de meeste omgevingen heeft die tabel
        // nooit bestaan, en dropColumn op een ontbrekende kolom is een fout.
        Schema::dropIfExists('lineup_substitutions');

        if (Schema::hasColumn('lineups', 'substitution_blocks')) {
            Schema::table('lineups', function (Blueprint $table) {
                $table->dropColumn('substitution_blocks');
            });
        }
    }

    public function down(): void
    {
        Schema::table('lineup_players', function (Blueprint $table) {
            $table->dropIndex(['lineup_id', 'period']);
            $table->dropColumn('period');
        });

        Schema::table('lineups', function (Blueprint $table) {
            $table->dropColumn('periods');
        });
    }
};
