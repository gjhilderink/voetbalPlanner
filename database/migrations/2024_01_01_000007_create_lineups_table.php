<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lineups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('match_id')->constrained('matches')->cascadeOnDelete();
            $table->string('formation')->nullable(); // e.g. 4-3-3
            $table->text('tactical_notes')->nullable();
            $table->timestamps();
            $table->unique('match_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lineups');
    }
};
