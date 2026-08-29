<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Een wedstrijd die de coach zelf afgelast.
 *
 * Bewust eigen kolommen en niet de bestaande `status`: die wordt bij elke
 * Sportlink-ronde overschreven met wat de bond zegt. Zet een coach de status op
 * "afgelast" en meldt Sportlink de wedstrijd nog gewoon als gepland, dan zou de
 * afgelasting er de volgende ochtend weer uit staan - en gaat het halve elftal
 * voor niets naar het veld.
 *
 * Andersom blijft een afgelasting die wél uit Sportlink komt gewoon via
 * `status` binnenkomen; beide bronnen bestaan naast elkaar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->string('cancel_reason')->nullable()->after('cancelled_at');
            $table->foreignUuid('cancelled_by_user_id')->nullable()
                ->after('cancel_reason')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn(['cancelled_at', 'cancel_reason']);
        });
    }
};
