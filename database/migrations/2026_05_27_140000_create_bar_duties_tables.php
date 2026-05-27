<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bar_duties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('shift', 30);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();
        });

        Schema::create('bar_duty_member', function (Blueprint $table) {
            $table->foreignUuid('bar_duty_id')->constrained('bar_duties')->cascadeOnDelete();
            $table->foreignUuid('member_id')->constrained('members')->cascadeOnDelete();
            $table->primary(['bar_duty_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bar_duty_member');
        Schema::dropIfExists('bar_duties');
    }
};
