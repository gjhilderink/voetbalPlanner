<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('dressing_room')->nullable()->after('arrival_time');
        });

        Schema::create('match_coaches', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignUuid('member_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['match_id', 'member_id']);
        });

        Schema::create('match_cleaners', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignUuid('member_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['match_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_cleaners');
        Schema::dropIfExists('match_coaches');
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('dressing_room');
        });
    }
};
