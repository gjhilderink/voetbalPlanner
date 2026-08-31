<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Het uiterlijk van de app per club: een icoon en een startscherm.
 *
 * Losse bestanden naast `logo_path`, en niet het clublogo hergebruiken. Een
 * logo heeft meestal witruimte en zelden een vierkante verhouding; als icoon
 * levert dat een postzegel in het midden op. Een startscherm wil juist wél
 * ruimte rondom.
 *
 * Het icoon wordt hier alleen bewaard. Een app-icoon wisselen kan niet uit een
 * upload - iOS en Android laten alleen kiezen uit iconen die al in de build
 * zitten - maar het bestand hoort wel bij de club, en ligt hier klaar voor het
 * moment dat er een build per club komt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->string('app_icon_path')->nullable()->after('logo_path');
            $table->string('splash_path')->nullable()->after('app_icon_path');
            // Leeg = de primaire kleur van de club.
            $table->string('splash_bg_color', 7)->nullable()->after('splash_path');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn(['app_icon_path', 'splash_path', 'splash_bg_color']);
        });
    }
};
