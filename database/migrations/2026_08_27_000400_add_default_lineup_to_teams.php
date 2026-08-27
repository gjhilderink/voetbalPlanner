<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De standaardopstelling van een elftal.
 *
 * Als kolom bij het team en niet als extra rij in `lineups`: die tabel hangt met
 * een verplichte sleutel aan een wedstrijd, en die nullable maken raakt een
 * vreemde sleutel en een unieke index op een tabel die al in gebruik is. Een
 * standaardopstelling is bovendien geen wedstrijd maar een sjabloon.
 *
 * JSON en geen aparte tabel met rijen per speler: er wordt nooit op gezocht of
 * gefilterd, hij wordt altijd in zijn geheel gelezen en in zijn geheel
 * overschreven. Bij het inladen worden de leden alsnog tegen de huidige selectie
 * gehouden, dus een speler die intussen weg is valt vanzelf af.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->json('default_lineup')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('default_lineup');
        });
    }
};
