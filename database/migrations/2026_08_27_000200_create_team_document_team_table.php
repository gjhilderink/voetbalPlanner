<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Een document kan aan meerdere elftallen hangen.
 *
 * Was één team_id; een draaiboek voor de zaterdagjeugd geldt vaak voor drie of
 * vier elftallen tegelijk, en dat werd dan hetzelfde bestand meerdere keren
 * uploaden.
 *
 * Geen enkel elftal gekoppeld blijft "hele club" betekenen — zelfde afspraak als
 * eerst, alleen nu uitgedrukt als een lege koppeling in plaats van een lege
 * kolom. Zelfde vorm als agenda_item_team.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_document_team', function (Blueprint $table) {
            $table->foreignUuid('team_document_id')->constrained('team_documents')->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained('teams')->cascadeOnDelete();
            $table->primary(['team_document_id', 'team_id']);
            $table->index('team_id');
        });

        // Bestaande koppelingen meenemen. De sectie draait pas sinds vandaag, dus
        // dit zijn er weinig — maar ze stilzwijgend laten vallen zou betekenen
        // dat een document ineens voor de hele club zichtbaar wordt.
        if (Schema::hasColumn('team_documents', 'team_id')) {
            $rijen = DB::table('team_documents')
                ->whereNotNull('team_id')
                ->get(['id', 'team_id']);

            foreach ($rijen as $rij) {
                DB::table('team_document_team')->insertOrIgnore([
                    'team_document_id' => $rij->id,
                    'team_id'          => $rij->team_id,
                ]);
            }

            Schema::table('team_documents', function (Blueprint $table) {
                $table->dropConstrainedForeignId('team_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('team_documents', function (Blueprint $table) {
            $table->foreignUuid('team_id')->nullable()->after('club_id')
                  ->constrained()->nullOnDelete();
        });

        // Terug naar één elftal: de eerste koppeling wint, de rest vervalt.
        foreach (DB::table('team_document_team')->get() as $rij) {
            DB::table('team_documents')
                ->where('id', $rij->team_document_id)
                ->whereNull('team_id')
                ->update(['team_id' => $rij->team_id]);
        }

        Schema::dropIfExists('team_document_team');
    }
};
