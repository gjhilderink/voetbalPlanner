<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Waar een bestelling vandaan komt.
 *
 * Sinds de club vouchers kan uitgeven staan er twee soorten in dezelfde lijst:
 * een aankoop uit de winkel, en een stapel codes die de club zelf heeft
 * afgedrukt. Die tweede is nooit betaald en heeft geen koper die op een mail
 * zit te wachten. Zonder dit onderscheid ziet een beheerder een bestelling van
 * € 0 staan en vraagt hij zich af wie dat besteld heeft - en zou "Mail opnieuw
 * sturen" de kaarten naar het adres van de beheerder zelf sturen.
 *
 * Een kolom en geen afleiding uit "geen transactie-id": een mislukte koppeling
 * met Pay.nl ziet er van buiten precies zo uit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('source', 16)->default('shop')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
