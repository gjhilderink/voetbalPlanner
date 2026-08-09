<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_team', function (Blueprint $table) {
            // Functie van de gebruiker per gekoppeld team (coach/leider/assistent/
            // speler). Default 'coach' omdat user_team historisch een beheer-
            // toewijzing was; bestaande koppelingen behouden zo hun beheerrechten.
            $table->string('role')->default('coach')->after('team_id');
        });

        // Expliciete backfill (naast de kolom-default) voor de zekerheid.
        DB::table('user_team')->update(['role' => 'coach']);
    }

    public function down(): void
    {
        Schema::table('user_team', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
