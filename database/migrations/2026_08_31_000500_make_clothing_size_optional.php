<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Een nummer zonder maat moet kunnen.
 *
 * De regel in member_clothing_sizes was een maat-opgave waar later een nummer
 * bij is gekomen. Daardoor kon je een nummer alleen bewaren als er al een maat
 * stond, en kreeg je anders "Kies eerst een maat" te zien. Terwijl het rugnummer
 * op een shirt niets met de maat te maken heeft: het ene weet je vaak eerder dan
 * het andere.
 *
 * De regel gaat dus over "wat is er van dit kledingstuk bekend", met allebei de
 * velden los invulbaar. De unieke sleutel op lid + kledingstuk blijft, dus er
 * komt geen tweede regel bij.
 */
return new class extends Migration
{
    public function up(): void
    {
        // De sleutel eerst los: MySQL laat een kolom niet wijzigen zolang er een
        // vreemde sleutel op staat.
        Schema::table('member_clothing_sizes', function (Blueprint $table) {
            $table->dropForeign(['clothing_size_id']);
        });

        Schema::table('member_clothing_sizes', function (Blueprint $table) {
            $table->uuid('clothing_size_id')->nullable()->change();
        });

        Schema::table('member_clothing_sizes', function (Blueprint $table) {
            $table->foreign('clothing_size_id')
                ->references('id')
                ->on('clothing_sizes')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Terugdraaien kan alleen als er geen regels zonder maat meer staan;
        // die zouden anders de kolom niet gevuld krijgen.
        Schema::table('member_clothing_sizes', function (Blueprint $table) {
            $table->dropForeign(['clothing_size_id']);
        });

        \Illuminate\Support\Facades\DB::table('member_clothing_sizes')
            ->whereNull('clothing_size_id')
            ->delete();

        Schema::table('member_clothing_sizes', function (Blueprint $table) {
            $table->uuid('clothing_size_id')->nullable(false)->change();
        });

        Schema::table('member_clothing_sizes', function (Blueprint $table) {
            $table->foreign('clothing_size_id')
                ->references('id')
                ->on('clothing_sizes')
                ->cascadeOnDelete();
        });
    }
};
