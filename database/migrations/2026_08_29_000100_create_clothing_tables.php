<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kledingmaten per lid.
 *
 * Drie tabellen, want er zijn drie dingen die los van elkaar veranderen: welke
 * kledingstukken een club uitdeelt, welke maten er bij zo'n stuk horen, en wat
 * een lid heeft opgegeven.
 *
 * De maten hangen aan het kledingstuk en niet aan de club. Anders zou "sokken,
 * maat L" moeten kunnen naast "sokken, maat 41-46", en dat is precies de soort
 * lijst waar niemand meer uit komt.
 *
 * De keuze van een lid staat in een eigen tabel met een unieke sleutel op lid +
 * kledingstuk: één maat per kledingstuk per lid. Wie hem heeft ingevuld staat
 * erbij, want drie partijen mogen dat doen - het lid zelf, zijn ouder en de
 * kledingcommissie - en zonder dat veld is achteraf niet te zien of de commissie
 * iets heeft overschreven wat het lid zelf had opgegeven.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clothing_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            // Uit de roulatie halen zonder de opgegeven maten weg te gooien: een
            // tas die dit seizoen niet wordt uitgedeeld, is volgend jaar weer
            // gewoon terug.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['club_id', 'sort_order']);
        });

        Schema::create('clothing_sizes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('clothing_item_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // De maten van één kledingstuk worden altijd in volgorde opgehaald.
            $table->index(['clothing_item_id', 'sort_order']);
        });

        Schema::create('member_clothing_sizes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('clothing_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('clothing_size_id')->constrained()->cascadeOnDelete();
            // Wie het als laatste heeft ingevuld. Het account mag verdwijnen; de
            // maat blijft dan gewoon staan.
            $table->foreignUuid('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['member_id', 'clothing_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_clothing_sizes');
        Schema::dropIfExists('clothing_sizes');
        Schema::dropIfExists('clothing_items');
    }
};
