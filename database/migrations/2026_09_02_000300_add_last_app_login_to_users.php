<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wanneer heeft iemand voor het laatst in de app ingelogd?
 *
 * last_login_at bestond al, maar dat wordt gestempeld bij elke aanmelding via
 * de auth-guard - dus ook als een beheerder de portal opent. Voor de vraag
 * "gebruiken onze leden de app eigenlijk?" is dat geen bruikbaar antwoord: een
 * bestuurder die dagelijks in de portal zit, ziet er precies zo uit als een
 * speler die de app trouw gebruikt.
 *
 * Vandaar een eigen kolom, die alleen wordt gezet door de twee wegen waarlangs
 * de app binnenkomt: inloggen met een wachtwoord op de API, en de magic link.
 *
 * Bestaande accounts beginnen leeg. Wie al ingelogd was, verschijnt zodra hij
 * de app weer opent - de vorige keer is niet met terugwerkende kracht te weten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_app_login_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_app_login_at');
        });
    }
};
