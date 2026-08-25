<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De pasfoto uit Sportlink, los van profile_photo.
 *
 * Twee kolommen en niet één: profile_photo is wat iemand zelf in de app upload
 * en moet een sync overleven. Zou de sync daarin schrijven, dan zet hij bij elke
 * ronde de eigen keuze van de gebruiker terug naar de clubpasfoto.
 *
 * De hash is de md5 van de aangeleverde base64. Zonder die vergelijking schrijft
 * elke sync alle foto's opnieuw weg, terwijl ze jarenlang hetzelfde blijven.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('sportlink_photo')->nullable()->after('profile_photo');
            $table->string('sportlink_photo_hash', 32)->nullable()->after('sportlink_photo');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['sportlink_photo', 'sportlink_photo_hash']);
        });
    }
};
