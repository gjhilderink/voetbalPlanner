<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categorieën voor de verenigingsagenda, per club beheerbaar. De kleur wordt
 * één-op-één doorgegeven aan de app zodat categorieën daar visueel te
 * onderscheiden zijn; de slug is de stabiele sleutel voor het filter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained()->cascadeOnDelete();

            $table->string('name', 60);
            $table->string('slug', 60);
            $table->string('color', 7)->default('#16a34a');
            $table->string('icon', 60)->default('event');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['club_id', 'slug']);
            $table->index(['club_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_categories');
    }
};
