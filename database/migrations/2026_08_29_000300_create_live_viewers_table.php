<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wie kijkt er op dit moment mee met een live wedstrijdverslag.
 *
 * Er is geen websocket-verbinding om aanwezigheid uit af te leiden, maar die
 * is ook niet nodig: zowel de app als de publieke deellink vraagt de toestand
 * elke tien seconden opnieuw op. Elk van die verzoeken is een teken van leven,
 * en dat is precies wat hier wordt vastgelegd.
 *
 * Eén rij per kijker per wedstrijd, dankzij de unieke sleutel. Zonder die
 * sleutel zou de tabel met zes rijen per kijker per minuut groeien; nu wordt
 * elke poll een overschrijving van één kolom.
 *
 * Bewust een eigen tabel en geen cache-sleutel met een verzameling erin: de
 * cache draait op deze hosting tóch op de database, en een verzameling die bij
 * elke poll gelezen, aangepast en teruggeschreven wordt is een race waarin twee
 * gelijktijdige kijkers elkaar wegschrijven.
 *
 * Privacy: geen IP-adres en geen rauwe sessie-id. Een meekijker zonder account
 * is een hash, en die rij wordt binnen een dag opgeruimd.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_viewers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('match_id')->constrained('matches')->cascadeOnDelete();

            // 'u:<user-id>' voor de app, 's:<hash van de sessie>' voor de deellink.
            $table->string('viewer_key', 64);

            // app | web — puur om achteraf te kunnen zien waar het kijken vandaan komt.
            $table->string('source', 8);

            // Geen timestamps(): alleen het laatste teken van leven telt.
            $table->timestamp('last_seen_at');

            $table->unique(['match_id', 'viewer_key']);
            $table->index(['match_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_viewers');
    }
};
