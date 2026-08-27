<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documenten bij een elftal of bij de hele club: spelregels, formulieren,
 * draaiboeken.
 *
 * team_id is nullable en betekent dan "de hele club" — de nieuwe spelregels van
 * de KNVB gaan iedereen aan, een teamdraaiboek niet.
 *
 * De bestandsnaam op schijf is een willekeurige sleutel, niet de oorspronkelijke
 * naam. Dat is nodig omdat de link gedeeld moet kunnen worden in een appbericht
 * of een mail en dus zonder inloggen werkt: een raadbare naam zou de rest van de
 * documenten ook vindbaar maken. original_name bewaart wat de gebruiker ziet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->string('description')->nullable();

            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // De lijst wordt altijd per club opgehaald, en meestal per elftal.
            $table->index(['club_id', 'team_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_documents');
    }
};
