<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vlagger (grensrechter) per wedstrijd — een lid uit het team, gekozen door de
 * coach in de app. Zelfde patroon als coach_id / fruit_hero_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->foreignUuid('vlagger_id')
                  ->nullable()
                  ->after('fruit_hero_id')
                  ->constrained('members')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vlagger_id');
        });
    }
};
