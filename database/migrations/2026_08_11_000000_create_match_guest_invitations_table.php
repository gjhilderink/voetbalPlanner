<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gastspeler-uitnodigingen: een coach nodigt een lid (uit een ander team) uit
 * voor één wedstrijd. Informatief (geen accept/afwijzen); de gast krijgt voor
 * de duur (expires_at) toegang tot die wedstrijd + een push. Vereenvoudigde
 * variant van guardian_links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_guest_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('club_id')->constrained()->cascadeOnDelete();

            $table->foreignUuid('match_id')
                  ->constrained('matches')
                  ->cascadeOnDelete();

            // De uitgenodigde gastspeler.
            $table->foreignUuid('member_id')
                  ->constrained('members')
                  ->cascadeOnDelete();

            // Team waaruit de gast gekozen is (context).
            $table->foreignUuid('team_id')
                  ->nullable()
                  ->constrained('teams')
                  ->nullOnDelete();

            // De coach die uitnodigde.
            $table->foreignUuid('invited_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->enum('status', ['active', 'revoked'])->default('active');

            $table->foreignUuid('revoked_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->dateTime('revoked_at')->nullable();

            // Toegang verloopt automatisch kort na de wedstrijd.
            $table->dateTime('expires_at');

            $table->timestamps();

            $table->index(['member_id', 'status']);
            $table->index(['match_id']);
            $table->index(['club_id', 'status']);
            // Geen dubbele uitnodiging voor hetzelfde lid + dezelfde wedstrijd.
            $table->unique(['match_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_guest_invitations');
    }
};
