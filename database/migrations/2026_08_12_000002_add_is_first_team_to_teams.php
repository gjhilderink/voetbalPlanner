<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Markeert het eerste elftal van de club (handmatig, blijft behouden bij sync).
 * Gebruikt o.a. in de bardienst-planner om de wedstrijd bovenaan te tonen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('is_first_team')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('is_first_team');
        });
    }
};
