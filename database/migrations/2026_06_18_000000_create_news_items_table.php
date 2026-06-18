<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title', 200);
            $table->longText('body');
            $table->string('image_path')->nullable();
            $table->enum('category', ['jeugd', 'senioren', 'algemeen'])->default('algemeen');

            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->index(['club_id', 'is_published', 'published_at']);
            $table->index(['club_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_items');
    }
};
