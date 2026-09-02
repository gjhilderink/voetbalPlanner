<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Het nummer op een kledingstuk is een opschrift, geen getal.
 *
 * Het stond als smallint, en daarmee is 040 hetzelfde als 40. Op het shirt is
 * dat niet hetzelfde: daar staat wat er staat, met voorloopnul en al. Wie zijn
 * broek zoekt in een doos met tachtig stuks heeft aan "40" niets als er 040 op
 * geborduurd is.
 *
 * Bestaande waarden veranderen niet van betekenis: 40 wordt "40".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_clothing_sizes', function (Blueprint $table) {
            $table->string('number', 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Terug naar een getal kan alleen als er niets in staat wat geen getal
        // is. Wat daar niet in past gaat leeg, want een halve waarde bewaren is
        // erger dan geen.
        \Illuminate\Support\Facades\DB::table('member_clothing_sizes')
            ->whereNotNull('number')
            ->where('number', 'not regexp', '^[0-9]{1,3}$')
            ->update(['number' => null]);

        Schema::table('member_clothing_sizes', function (Blueprint $table) {
            $table->unsignedSmallInteger('number')->nullable()->change();
        });
    }
};
