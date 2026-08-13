<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verenigingsactiviteiten die los staan van een elftal, wedstrijd of training
 * (toernooi, bazaar, clubavond, vrijwilligersactiviteit).
 *
 * Zichtbaarheid werkt via 'audience': 'everyone' voor de hele club, of
 * 'selection' met een willekeurige combinatie van elftallen (pivot) en
 * staf-/vrijwilligersgroepen (pivot). Datums staan als Nederlandse wandkloktijd
 * in de kolom, net als matches.match_datetime — zie AgendaItem en IcsBuilder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agenda_category_id')->nullable()
                ->constrained('agenda_categories')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title', 200);
            $table->string('summary', 300)->nullable();
            $table->longText('description')->nullable();
            $table->string('image_path')->nullable();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_all_day')->default(false);

            $table->string('location', 200)->nullable();
            $table->string('location_url', 500)->nullable();
            $table->string('external_url', 500)->nullable();
            $table->text('extra_info')->nullable();

            $table->string('audience', 20)->default('everyone');

            $table->boolean('registration_enabled')->default(false);
            $table->dateTime('registration_closes_at')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('allow_guests')->default(false);
            $table->boolean('show_participants')->default(true);

            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_highlighted')->default(false);

            $table->timestamps();

            $table->index(['club_id', 'is_published', 'starts_at']);
            $table->index(['club_id', 'agenda_category_id', 'starts_at']);
            $table->index(['club_id', 'audience']);
        });

        Schema::create('agenda_item_team', function (Blueprint $table) {
            $table->foreignUuid('agenda_item_id')->constrained('agenda_items')->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained('teams')->cascadeOnDelete();
            $table->primary(['agenda_item_id', 'team_id']);
            $table->index('team_id');
        });

        Schema::create('agenda_item_staff_group', function (Blueprint $table) {
            $table->foreignUuid('agenda_item_id')->constrained('agenda_items')->cascadeOnDelete();
            $table->foreignUuid('staff_group_id')->constrained('staff_groups')->cascadeOnDelete();
            $table->primary(['agenda_item_id', 'staff_group_id'], 'agenda_item_staff_group_primary');
            $table->index('staff_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_item_staff_group');
        Schema::dropIfExists('agenda_item_team');
        Schema::dropIfExists('agenda_items');
    }
};
