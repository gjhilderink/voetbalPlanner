<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teamsfeer: één stemming per gebruiker per team per week. De app toont het
 * gemiddelde als smiley op het dashboard.
 *
 * Bewust per week en niet per dag: de vraag is hoe de sfeer in het team is,
 * niet hoe iemand zich vandaag voelt. Een nieuwe stem in dezelfde week
 * overschrijft de vorige (unieke sleutel op team + gebruiker + week).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_moods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Nullable: club_id is op teams en users óók nullable, en een
            // stemming zonder club blijft bruikbaar.
            $table->foreignUuid('club_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('member_id')->nullable()->constrained('members')->nullOnDelete();
            // 1 = slecht, 2 = matig, 3 = goed, 4 = top.
            $table->unsignedTinyInteger('score');
            // ISO-week als 'YYYY-WW', zodat de unieke sleutel simpel blijft.
            $table->string('week', 7);
            $table->timestamps();

            $table->unique(['team_id', 'user_id', 'week']);
            $table->index(['team_id', 'week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_moods');
    }
};
