<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Het rugnummer hoorde alleen bij een opstelling, dus moest je het per wedstrijd
 * opnieuw invullen. Het hoort bij de speler: die draagt het hele seizoen
 * hetzelfde nummer.
 *
 * Geen unieke index: een lid kan in meerdere elftallen spelen, en twee teams
 * hebben allebei een nummer 7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedTinyInteger('shirt_number')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('shirt_number');
        });
    }
};
