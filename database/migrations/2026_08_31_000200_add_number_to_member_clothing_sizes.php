<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Het nummer op een kledingstuk, per lid.
 *
 * Hoort bij het lid en niet bij het kledingstuk: het rugnummer op een shirt is
 * van de speler, niet van "shirt". Nullable, want lang niet elk kledingstuk
 * heeft er een - op een tas of een paar sokken staat niets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_clothing_sizes', function (Blueprint $table) {
            // Klein geheel getal: rugnummers lopen tot 99, en met 65535 aan
            // ruimte hoeft niemand ooit na te denken over de bovengrens.
            $table->unsignedSmallInteger('number')->nullable()->after('clothing_size_id');
        });
    }

    public function down(): void
    {
        Schema::table('member_clothing_sizes', function (Blueprint $table) {
            $table->dropColumn('number');
        });
    }
};
