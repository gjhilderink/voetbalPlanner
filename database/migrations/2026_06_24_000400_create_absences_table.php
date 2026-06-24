<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            // 'training' | 'match'
            $table->string('type');
            // Wedstrijd-afmelding
            $table->foreignUuid('match_id')->nullable()->constrained('matches')->cascadeOnDelete();
            // Training-afmelding: schema + de concrete trainingsdag
            $table->foreignUuid('training_schedule_id')->nullable()
                  ->constrained('training_schedules')->cascadeOnDelete();
            $table->date('training_date')->nullable();
            $table->string('reason');
            $table->timestamps();

            $table->index(['member_id', 'type']);
            $table->index(['match_id']);
            $table->index(['training_schedule_id', 'training_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absences');
    }
};
