<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bug_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('club_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title', 200);
            $table->text('description');

            $table->string('app_version', 50)->nullable();
            $table->string('platform', 50)->nullable();
            $table->string('device_info', 255)->nullable();

            // Lijst met opgeslagen screenshot paden (storage/public/...)
            $table->json('screenshot_paths')->nullable();

            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])
                  ->default('open');
            $table->text('admin_notes')->nullable();

            $table->timestamps();

            $table->index(['club_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bug_reports');
    }
};
