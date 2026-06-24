<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Gekoppelde feature (automatisch gegenereerd); nullonDelete zodat een
            // verwijderde feature de release note niet meesleept.
            $table->foreignUuid('feature_id')->nullable()->constrained('features')->nullOnDelete();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_notes');
    }
};
