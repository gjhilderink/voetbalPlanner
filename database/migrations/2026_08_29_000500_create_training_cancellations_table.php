<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eén afgelaste training.
 *
 * Trainingen bestaan niet als rij: ze worden berekend uit het herhaalschema van
 * het elftal ("elke dinsdag om 18:30"). Er is dus niets om een vlaggetje op te
 * zetten. Daarom leggen we alleen de uitzonderingen vast - de keren dat een
 * training níet doorgaat - en blijft het schema zelf ongemoeid.
 *
 * De datum hoort bij de sleutel: dezelfde training kan volgende week gewoon
 * doorgaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_cancellations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('training_schedule_id')
                ->constrained('training_schedules')->cascadeOnDelete();
            $table->date('date');
            $table->string('reason')->nullable();
            $table->foreignUuid('cancelled_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Eén afgelasting per training; opnieuw afgelasten werkt de reden bij.
            $table->unique(['training_schedule_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_cancellations');
    }
};
