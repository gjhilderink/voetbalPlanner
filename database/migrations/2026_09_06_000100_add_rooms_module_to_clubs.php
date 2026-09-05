<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De module Ruimtes, per club aan of uit.
 *
 * Zoals access_enabled en ticketshop_enabled: niet elke club heeft ruimtes om
 * te verdelen, en wat uitstaat hoort nergens zichtbaar te zijn - niet in de
 * portal en niet in de app.
 *
 * Standaard uit. Een module die vanzelf aangaat bij clubs die er niet om
 * gevraagd hebben levert vragen op in plaats van gebruik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->boolean('rooms_enabled')->default(false)->after('ticketshop_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('rooms_enabled');
        });
    }
};
