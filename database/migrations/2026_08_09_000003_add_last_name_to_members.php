<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Achternaam apart opgeslagen (uit Sportlink), zodat ouders een kind
            // betrouwbaar op lidnummer + achternaam kunnen koppelen.
            $table->string('last_name')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('last_name');
        });
    }
};
