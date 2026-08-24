<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De opstelling was een platte lijst met een positie per speler. Voor het veld
 * in de app is dat te weinig: daar staat een speler op een plek, niet in een
 * categorie. Vandaar coördinaten per speler.
 *
 * De coördinaten zijn verhoudingen (0..1) en geen pixels, zodat dezelfde
 * opstelling op elk schermformaat klopt.
 *
 * published_at bepaalt of spelers de opstelling al mogen zien. De coach schuift
 * liever rustig zonder dat er ondertussen tien ouders bellen over een plek die
 * een kwartier later toch weer verandert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lineups', function (Blueprint $table) {
            $table->unsignedTinyInteger('players_on_field')->default(11)->after('formation');
            $table->string('match_format')->nullable()->after('players_on_field');
            $table->timestamp('published_at')->nullable()->after('match_format');
        });

        Schema::table('lineup_players', function (Blueprint $table) {
            $table->float('slot_x')->nullable()->after('position');
            $table->float('slot_y')->nullable()->after('slot_x');
            // Volgorde op de bank; bepaalt ook wie er als eerste in aanmerking
            // komt bij het automatisch invullen.
            $table->unsignedTinyInteger('sort_order')->default(0)->after('is_substitute');
        });
    }

    public function down(): void
    {
        Schema::table('lineups', function (Blueprint $table) {
            $table->dropColumn(['players_on_field', 'match_format', 'published_at']);
        });

        Schema::table('lineup_players', function (Blueprint $table) {
            $table->dropColumn(['slot_x', 'slot_y', 'sort_order']);
        });
    }
};
