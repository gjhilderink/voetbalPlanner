<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De verzameltijd zoals Sportlink hem doorgaf.
 *
 * Precies dezelfde reden als sportlink_datetime bij de aanvangstijd: de
 * synchronisatie schrijft arrival_time elke ronde opnieuw met wat de bond zegt.
 * Dat gold ook al voor de verzameltijd die een beheerder in de portal invulde -
 * die werd stilzwijgend overschreven, en dat viel niemand op omdat er verder
 * niets van te zien was.
 *
 * Is deze kolom gevuld, dan heeft iemand de verzameltijd met de hand gezet. De
 * synchronisatie laat arrival_time dan met rust en schrijft hier alleen nog bij
 * wat Sportlink inmiddels zegt, zodat terugzetten één handeling blijft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->time('sportlink_arrival_time')->nullable()->after('arrival_time');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('sportlink_arrival_time');
        });
    }
};
