<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De ticketshop: kaarten verkopen voor een activiteit.
 *
 * Bouwt door op de toegangscontrole. Een verkocht kaartje ís een toegangscode
 * met max_uses = 1 en de naam van de koper erbij; er komt dus geen tweede soort
 * code naast. access_codes krijgt alleen een verwijzing naar de bestelling.
 *
 * Bedragen staan in hele centen in een integer. Dit is het eerste geld in deze
 * applicatie - de tarievenpagina bewaart bedragen als tekst, maar dat is
 * marketingtekst en geen boekhouding. Met centen in een integer kan een optelling
 * niet stilletjes een cent verliezen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            // Los van access_enabled: een club kan prima aan de deur willen
            // controleren zonder online te verkopen. Andersom niet, en dat
            // bewaakt de instellingenpagina.
            $table->boolean('ticketshop_enabled')->default(false)->after('access_enabled');
        });

        Schema::create('ticket_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agenda_item_id')->constrained('agenda_items')->cascadeOnDelete();

            $table->string('name', 120);
            $table->string('description', 300)->nullable();

            $table->unsignedInteger('price_cents')->default(0);
            // Leeg = onbeperkt. Nul is iets anders: dat is uitverkocht.
            $table->unsignedSmallInteger('stock')->nullable();
            $table->unsignedSmallInteger('max_per_order')->default(10);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['agenda_item_id', 'sort_order']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agenda_item_id')->constrained('agenda_items')->cascadeOnDelete();

            // Kort en voorleesbaar, voor in de mail en aan de telefoon. Geen
            // doorlopende nummering: die vraagt om een teller met een slot, en
            // een bestelnummer hoeft nergens opeenvolgend te zijn.
            $table->string('order_number', 16)->unique();
            // Waarmee de koper zijn bestelling terugvindt zonder in te loggen.
            $table->string('public_token', 64)->unique();

            $table->string('buyer_name', 150);
            $table->string('buyer_email', 190);

            $table->unsignedInteger('total_cents')->default(0);
            $table->string('status', 16)->default('pending');

            $table->string('paynl_transaction_id', 64)->nullable();
            $table->dateTime('paid_at')->nullable();
            // Tot wanneer de gereserveerde voorraad vastgehouden wordt.
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('mail_sent_at')->nullable();

            $table->timestamps();

            $table->index(['club_id', 'status', 'created_at']);
            $table->index(['agenda_item_id', 'status']);
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            // Verdwijnt de kaartsoort, dan blijft de bestelregel staan: wat er
            // ooit verkocht is verandert niet meer.
            $table->foreignUuid('ticket_type_id')->nullable()->constrained('ticket_types')->nullOnDelete();

            // Naam en prijs als momentopname. Past de club later de prijs aan,
            // dan hoort een bestelling van vorig seizoen niet mee te veranderen.
            $table->string('type_name', 120);
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('line_total_cents');

            $table->timestamps();

            $table->index(['ticket_type_id']);
        });

        Schema::table('access_codes', function (Blueprint $table) {
            // Gevuld bij een gekocht kaartje, leeg bij een code die de club zelf
            // heeft aangemaakt of geïmporteerd.
            $table->foreignUuid('order_id')->nullable()->after('agenda_item_id')
                ->constrained('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('access_codes', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn('order_id');
        });

        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('ticket_types');

        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('ticketshop_enabled');
        });
    }
};
