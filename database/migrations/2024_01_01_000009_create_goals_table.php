<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignUuid('scorer_id')->constrained('members')->cascadeOnDelete();
            $table->foreignUuid('assist_id')->nullable()->constrained('members')->nullOnDelete();
            $table->integer('minute')->nullable();
            $table->boolean('is_own_goal')->default(false);
            $table->boolean('is_penalty')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
