<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_links', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Club-scoping (zelfde patroon als bar_duties, staff_groups)
            $table->foreignUuid('club_id')->constrained()->cascadeOnDelete();

            // De ouder/verzorger (initiator van het verzoek)
            $table->foreignUuid('guardian_member_id')
                  ->constrained('members')
                  ->cascadeOnDelete();

            // Het kind/lid waarmee gekoppeld wordt
            $table->foreignUuid('child_member_id')
                  ->constrained('members')
                  ->cascadeOnDelete();

            $table->enum('status', ['pending', 'approved', 'rejected', 'revoked'])
                  ->default('pending');

            // Beveiligd token zodat het kind het verzoek kan opzoeken
            $table->string('request_token', 64)->unique();

            // Wie heeft gereageerd (accepteren/weigeren)
            $table->foreignUuid('resolved_by_member_id')
                  ->nullable()
                  ->constrained('members')
                  ->nullOnDelete();

            $table->dateTime('resolved_at')->nullable();

            // Wie heeft ingetrokken (kind, ouder of beheerder)
            $table->foreignUuid('revoked_by_member_id')
                  ->nullable()
                  ->constrained('members')
                  ->nullOnDelete();

            $table->dateTime('revoked_at')->nullable();

            // Verzoek verloopt na 14 dagen als het kind niet reageert
            // dateTime i.p.v. timestamp: MySQL strict mode staat timestamp NOT NULL
            // zonder DEFAULT niet toe wanneer er al meerdere timestamp-kolommen zijn.
            $table->dateTime('expires_at');

            $table->timestamps();

            // Performance-indexes
            $table->index(['child_member_id', 'status']);
            $table->index(['guardian_member_id', 'status']);
            $table->index(['club_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_links');
    }
};
