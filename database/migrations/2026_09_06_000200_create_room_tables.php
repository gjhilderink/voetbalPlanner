<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ruimtes van de club en wie ze wanneer gebruikt.
 *
 * Tot nu toe was een ruimte een vrij tekstveld: `dressing_room` op een
 * wedstrijd en op een trainingsschema. Wie de kantine wilde gebruiken belde of
 * appte, en een dubbele afspraak bleek pas als er twee groepen voor dezelfde
 * deur stonden.
 *
 * Eén tabel voor eigen én externe reserveringen. Een afspraak die rechtstreeks
 * in Outlook is gemaakt komt hier als `source = 'outlook'` binnen te staan. Zo
 * is er één plek waar de bezetting staat en één overlapcontrole, in plaats van
 * twee bronnen die je bij elke vraag moet samenvoegen.
 *
 * Tijden staan als Nederlandse wandkloktijd, net als agenda_items.starts_at en
 * matches.match_datetime. Er wordt nergens naar UTC gerekend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            // Kleur van het blok in het weekrooster. Met vijf ruimtes naast
            // elkaar is kleur sneller dan lezen.
            $table->string('color', 7)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            // De postbus van de ruimte in Microsoft 365. Dit is de koppelsleutel
            // en niet de naam: die mag hier anders heten dan daar.
            $table->string('ms_room_email')->nullable();
            // Het place-id uit Graph, alleen gevuld als de ruimte via "ophalen
            // uit Microsoft" is gevonden.
            $table->string('ms_room_id')->nullable();
            $table->timestamp('ms_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['club_id', 'name']);
            // Twee ruimtes op dezelfde postbus zou betekenen dat we elkaars
            // afspraken over en weer importeren. MySQL laat meerdere NULLs toe
            // in een unieke index, dus ruimtes zonder koppeling botsen niet.
            $table->unique(['club_id', 'ms_room_email']);
            $table->index(['club_id', 'is_active', 'sort_order']);
        });

        Schema::create('room_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('room_id')->constrained('rooms')->cascadeOnDelete();

            // Bewust nullOnDelete en geen cascade: wordt de activiteit
            // verwijderd, dan blijft de reservering staan. De ruimte was bezet,
            // en in Microsoft staat de afspraak ook nog.
            $table->foreignUuid('agenda_item_id')->nullable()
                ->constrained('agenda_items')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // De naam apart bewaren. Het account mag ooit weg; wie dit heeft
            // aangevraagd hoort dan nog leesbaar te zijn.
            $table->string('requester_name')->nullable();

            $table->string('title');
            $table->text('notes')->nullable();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->boolean('is_private')->default(false);
            $table->string('status', 20)->default('bevestigd');
            // portal | app | outlook
            $table->string('source', 20)->default('portal');

            $table->string('ms_event_id')->nullable();
            // Blijft gelijk als een afspraak tussen postbussen beweegt; hiermee
            // herkennen we bij het teruglezen onze eigen afspraak en maken we er
            // geen tweede, "externe" rij van.
            $table->string('ms_icaluid')->nullable();
            $table->timestamp('ms_synced_at')->nullable();
            $table->string('ms_last_error')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            // De overlapvraag: welke reservering van deze ruimte raakt dit
            // venster. Dit is de enige index die bij elke boeking wordt geraakt.
            $table->index(['room_id', 'starts_at', 'ends_at']);
            $table->index(['club_id', 'starts_at']);
            $table->index('source');
            // Maakt het teruglezen uit Microsoft idempotent: dezelfde afspraak
            // kan niet twee rijen opleveren.
            $table->unique(['room_id', 'ms_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_reservations');
        Schema::dropIfExists('rooms');
    }
};
