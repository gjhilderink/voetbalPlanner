<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->foreignUuid('club_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->unique(['key', 'club_id']);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['key', 'club_id']);
            $table->dropForeign(['club_id']);
            $table->dropColumn('club_id');
            $table->unique(['key']);
        });
    }
};
