<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('training_schedules', function (Blueprint $table) {
            // Kleedkamer voor de training (optioneel).
            $table->string('dressing_room')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('training_schedules', function (Blueprint $table) {
            $table->dropColumn('dressing_room');
        });
    }
};
