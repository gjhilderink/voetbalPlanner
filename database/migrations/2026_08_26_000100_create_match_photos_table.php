<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto's die teamleden en ouders bij een wedstrijd zetten.
 *
 * uploader_name staat er als losse kolom naast user_id. Die verwijzing valt weg
 * zodra een account wordt verwijderd (nullOnDelete), en dan zou er onder de foto
 * niemand meer staan terwijl het bijschrift juist de vraag beantwoordt wie hem
 * gemaakt heeft. Een naam op het moment van uploaden is daarvoor genoeg.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploader_name');
            // Pad onder de match_photos-disk, niet de volledige URL: een
            // domeinnaam hoort niet in de database.
            $table->string('path');
            $table->timestamps();

            // De lijst wordt altijd per wedstrijd opgehaald, nieuwste eerst.
            $table->index(['match_id', 'created_at']);
            // Het maximum per gebruiker wordt hierop geteld.
            $table->index(['match_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_photos');
    }
};
