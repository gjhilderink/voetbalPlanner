<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mag dit elftal na een wedstrijd een man of the match kiezen?
 *
 * Per elftal en niet per club: bij de jongste jeugd is een stemming over wie de
 * beste was eerder schadelijk dan leuk, terwijl de senioren er juist om vragen.
 * Standaard uit — een club die er niet om gevraagd heeft hoort niet ineens een
 * stemknop in de app te zien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('motm_enabled')->default(false)->after('is_first_team');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('motm_enabled');
        });
    }
};
