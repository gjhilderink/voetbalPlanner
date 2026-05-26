<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('external_id')->nullable()->unique();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->string('opponent');
            $table->dateTime('match_datetime');
            $table->string('location')->nullable();
            $table->boolean('is_home')->default(true);
            $table->string('status')->default('scheduled'); // scheduled|played|cancelled|postponed
            $table->integer('score_home')->nullable();
            $table->integer('score_away')->nullable();
            $table->time('arrival_time')->nullable();
            $table->foreignUuid('coach_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignUuid('fruit_hero_id')->nullable()->constrained('members')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
