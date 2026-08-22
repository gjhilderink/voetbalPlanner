<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loopt er nu een live verslag bij deze wedstrijd, en onder welke geheime link
 * is dat te volgen.
 *
 * De bestaande `status`-kolom blijft hier bewust buiten: die wordt door de
 * Sportlink-sync overschreven, en dan zou een lopend verslag zomaar kunnen
 * stoppen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->timestamp('live_started_at')->nullable()->after('notes');
            $table->timestamp('live_halftime_at')->nullable()->after('live_started_at');
            $table->timestamp('live_ended_at')->nullable()->after('live_halftime_at');
            // 64 tekens alfanumeriek, zelfde vorm als de magic-link-token.
            $table->string('live_token', 64)->nullable()->unique()->after('live_ended_at');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropUnique(['live_token']);
            $table->dropColumn(['live_started_at', 'live_halftime_at', 'live_ended_at', 'live_token']);
        });
    }
};
