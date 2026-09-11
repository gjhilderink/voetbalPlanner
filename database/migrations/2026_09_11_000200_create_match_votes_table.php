<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Man of the match: één stem per account per wedstrijd, anoniem.
 *
 * De unieke sleutel op wedstrijd + gebruiker ís de regel "je mag maar één keer
 * stemmen". Die hoort hier en niet alleen in de controller: twee keer snel
 * tikken op een trage verbinding komt anders als twee stemmen binnen.
 *
 * Anders dan bij de teamsfeer hiernaast (team_moods) is een stem definitief.
 * Daar overschrijft een nieuwe stem de vorige, want de vraag is hoe de sfeer nú
 * is. Hier gaat het om één wedstrijd die al gespeeld is: wie zijn stem mag
 * wijzigen kan wachten tot de stand bekend is en daarna de uitslag kantelen.
 *
 * user_id en niet member_id als sleutel: een ouder of een leider stemt vanaf een
 * account dat niet altijd een ledenrecord heeft. member_id gaat wel mee, maar
 * alleen ter informatie — zelfde opzet als bij de afmeldingen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Nullable: club_id is op teams en users óók nullable, en een stem
            // zonder club blijft bruikbaar.
            $table->foreignUuid('club_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('member_id')->nullable()->constrained('members')->nullOnDelete();
            // Op wie is gestemd.
            $table->foreignUuid('voted_member_id')->constrained('members')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['match_id', 'user_id']);
            $table->index(['match_id', 'voted_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_votes');
    }
};
