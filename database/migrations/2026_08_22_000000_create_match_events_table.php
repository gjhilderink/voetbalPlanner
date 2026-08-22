<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gebeurtenissen tijdens een wedstrijd: aftrap, doelpunten, wissels, kaarten,
 * rust en eindsignaal. Samen vormen ze het live verslag.
 *
 * Bewust een eigen tabel en niet de bestaande `goals` opgerekt: daar is
 * scorer_id verplicht, dus een doelpunt van de tegenstander past er niet in,
 * en wissels en kaarten al helemaal niet. Een eigen doelpunt met maker komt
 * in béíde tabellen te staan (zie goal_id), zodat de seizoenscijfers blijven
 * kloppen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('match_id')->constrained('matches')->cascadeOnDelete();

            // kickoff | halftime | second_half | fulltime | goal | card | substitution
            $table->string('type');
            $table->unsignedSmallInteger('minute')->nullable();

            // own | opponent — alleen bij goal en card.
            $table->string('side')->nullable();

            // Maker, gekaarte speler, of speler die erin komt.
            $table->foreignUuid('member_id')->nullable()->constrained('members')->nullOnDelete();
            // Assist, of speler die eruit gaat.
            $table->foreignUuid('related_member_id')->nullable()->constrained('members')->nullOnDelete();

            $table->string('card_type')->nullable();   // yellow | red
            $table->string('detail')->nullable();      // penalty | own_goal

            // Koppeling met de doelpuntenadministratie, zodat "ongedaan maken"
            // beide kanten opruimt.
            $table->foreignUuid('goal_id')->nullable()->constrained('goals')->nullOnDelete();

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Volgorde van het verslag: op registratiemoment, niet op minuut —
            // twee doelpunten in dezelfde minuut moeten hun volgorde houden.
            $table->index(['match_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_events');
    }
};
