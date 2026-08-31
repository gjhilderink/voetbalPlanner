<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Koppelt een handleiding-sectie aan een in-app rondleiding.
 *
 * `tour_id` is de sleutel uit TourDefinities in de app (bijvoorbeeld
 * 'wedstrijd_afgelasten'). Leeg betekent: geen rondleiding, en dan verschijnt
 * er ook geen knop. Bewust geen foreign key of enum - de lijst met
 * rondleidingen staat in de app-code, niet in de database, en die twee moeten
 * los van elkaar uitgerold kunnen worden.
 *
 * `tour_start_step` laat een sectie halverwege beginnen. Handig als twee
 * secties dezelfde rondleiding delen maar over een ander stuk gaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentations', function (Blueprint $table) {
            $table->string('tour_id')->nullable()->after('body');
            $table->unsignedSmallInteger('tour_start_step')->default(0)->after('tour_id');
        });
    }

    public function down(): void
    {
        Schema::table('documentations', function (Blueprint $table) {
            $table->dropColumn(['tour_id', 'tour_start_step']);
        });
    }
};
