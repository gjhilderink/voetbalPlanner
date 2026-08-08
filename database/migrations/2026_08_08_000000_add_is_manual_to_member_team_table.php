<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('member_team', function (Blueprint $table) {
            // Handmatig (via het admin-panel) aangemaakte koppelingen. De
            // Sportlink-sync laat deze met rust en koppelt ze nooit los.
            $table->boolean('is_manual')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('member_team', function (Blueprint $table) {
            $table->dropColumn('is_manual');
        });
    }
};
