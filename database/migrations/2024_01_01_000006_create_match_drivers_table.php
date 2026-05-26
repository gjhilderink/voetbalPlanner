<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignUuid('member_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['match_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_drivers');
    }
};
