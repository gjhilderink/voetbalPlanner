<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Het wisselschema: per wisselmoment wie eruit gaat en wie erin komt.
 *
 * Een aparte tabel en geen kolom op lineup_players, omdat één speler in
 * meerdere blokken kan wisselen en een wissel altijd twee spelers betreft.
 *
 * 'block' is het hoeveelste wisselmoment, niet een speelperiode: bij 2 blokken
 * heeft de wedstrijd drie perioden en wordt er twee keer gewisseld.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lineups', function (Blueprint $table) {
            $table->unsignedTinyInteger('substitution_blocks')->default(1)->after('match_format');
        });

        Schema::create('lineup_substitutions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('lineup_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('block');
            // nullOnDelete en niet cascade: verdwijnt een lid, dan blijft de rest
            // van het schema staan in plaats van dat de wissel stilletjes weg is.
            $table->foreignUuid('member_out_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignUuid('member_in_id')->nullable()->constrained('members')->nullOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['lineup_id', 'block']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lineup_substitutions');

        Schema::table('lineups', function (Blueprint $table) {
            $table->dropColumn('substitution_blocks');
        });
    }
};
