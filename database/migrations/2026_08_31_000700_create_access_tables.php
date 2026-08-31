<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Toegangscontrole bij een activiteit.
 *
 * Het agenda-item is het evenement; er is geen aparte lijsttabel. Dat scheelt
 * niet alleen een tabel: een activiteit die alleen "gratis voor leden" is heeft
 * helemaal geen codes, en zou anders een lege lijst nodig hebben om überhaupt
 * gescand te kunnen worden.
 *
 * Twee soorten toegang komen hier samen:
 *   - een uitgedeelde code, met een eigen teller (access_codes);
 *   - het lidnummer van een lid, dat werkt zodra het agenda-item op
 *     free_for_members staat. Die staan nergens als code opgeslagen - het lid
 *     bestaat al.
 *
 * access_entries houdt alleen geslaagde binnenkomsten bij, geen mislukte
 * pogingen. Dat is met opzet: de unieke sleutel op activiteit + lid ís de
 * controle op "deze is al binnen", en die kan alleen zijn werk doen als er niet
 * ook afgewezen scans in staan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            // Staat dit aan, dan is het lidnummer van elk clublid hier een
            // geldige toegangscode - één keer per activiteit.
            $table->boolean('free_for_members')->default(false)->after('capacity');
        });

        Schema::create('access_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // club_id staat erbij naast het agenda-item: de portal filtert op
            // club en dat hoort niet elke keer via een join te moeten.
            $table->foreignUuid('club_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agenda_item_id')->constrained('agenda_items')->cascadeOnDelete();

            $table->string('code', 64);
            // Vrij veld: een naam, een rijnummer, of niets.
            $table->string('label')->nullable();

            // Klein geheel getal: honderden keren is al veel voor één code, en
            // met 65535 aan ruimte hoeft niemand over de bovengrens na te denken.
            $table->unsignedSmallInteger('max_uses')->default(1);
            $table->unsignedSmallInteger('used_count')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Dezelfde code mag bij twee verschillende activiteiten bestaan;
            // binnen één activiteit niet.
            $table->unique(['agenda_item_id', 'code']);
            $table->index(['club_id', 'agenda_item_id']);
        });

        Schema::create('access_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agenda_item_id')->constrained('agenda_items')->cascadeOnDelete();

            // Precies één van deze twee is gevuld: een uitgedeelde code, of een
            // lid dat met zijn eigen lidnummer binnenkwam.
            $table->foreignUuid('access_code_id')->nullable()->constrained('access_codes')->cascadeOnDelete();
            $table->foreignUuid('member_id')->nullable()->constrained('members')->cascadeOnDelete();

            // Wie er scande. Het account mag verdwijnen; de binnenkomst blijft.
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->dateTime('entered_at');
            $table->timestamps();

            // Dit dwingt "één keer per lid per activiteit" af. In MySQL botsen
            // NULL-waarden niet in een unieke index, dus binnenkomsten op een
            // uitgedeelde code - die geen member_id hebben - lopen hier
            // ongehinderd langs.
            $table->unique(['agenda_item_id', 'member_id']);
            $table->index(['agenda_item_id', 'entered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_entries');
        Schema::dropIfExists('access_codes');

        Schema::table('agenda_items', function (Blueprint $table) {
            $table->dropColumn('free_for_members');
        });
    }
};
