<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verzendlog van release notes: één rij per (release note, ontvanger) per
 * verzending, zodat het admin-paneel kan tonen óf een note verzonden is en
 * naar wie/wanneer er gemaild is. scope = self (test) | selected | all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_note_sends', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('release_note_id')
                  ->constrained('release_notes')
                  ->cascadeOnDelete();

            // Ontvanger (het e-mailadres waarnaar gemaild is).
            $table->string('email');

            // Naar wie: self = test naar beheerder, selected = keuze, all = iedereen.
            $table->string('scope', 20)->default('all');

            // sent | failed.
            $table->string('status', 20)->default('sent');

            // De beheerder die de mail verstuurde.
            $table->foreignUuid('sent_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['release_note_id', 'status']);
            $table->index(['release_note_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_note_sends');
    }
};
