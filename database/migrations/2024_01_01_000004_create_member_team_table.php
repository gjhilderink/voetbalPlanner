<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_team', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('member_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('player');
            $table->string('season')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['member_id', 'team_id', 'season']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_team');
    }
};
