<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aan-/afmeldingen voor een agenda-item.
 *
 * Een aanmelding hoort bij een 'subject': een lid (member_id) of een account
 * zonder lidprofiel (user_id) — dezelfde tweedeling als bij bar_duty_user en
 * staff_group_user. Een ouder kan zich voor meerdere kinderen aanmelden, dus
 * (item, user) is géén unieke sleutel. Een unique over drie kolommen werkt
 * evenmin: MySQL beschouwt NULL-waarden als onderling verschillend, waardoor
 * dubbele rijen alsnog ontstaan. Vandaar subject_key ('m:<uuid>' of 'u:<uuid>')
 * als enige uniciteitsgarantie.
 *
 * Afmelden verwijdert de rij niet maar zet status op 'afgemeld', zodat het
 * beheer ziet wie expliciet heeft afgezegd. Tellingen filteren dus altijd op
 * status = 'aangemeld'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agenda_item_id')->constrained('agenda_items')->cascadeOnDelete();
            $table->foreignUuid('club_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('member_id')->nullable()->constrained('members')->cascadeOnDelete();

            $table->string('subject_key', 45);
            $table->string('name', 120);
            $table->string('status', 20)->default('aangemeld');
            $table->unsignedTinyInteger('guest_count')->default(0);
            $table->string('note', 255)->nullable();
            $table->timestamp('registered_at')->nullable();

            $table->timestamps();

            $table->unique(['agenda_item_id', 'subject_key']);
            $table->index(['agenda_item_id', 'status']);
            $table->index(['club_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_registrations');
    }
};
