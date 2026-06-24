<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Losse accounts (User) zonder lidnummer kunnen zich nu ook af-/aanmelden:
        // de afmelding hangt dan aan user_id i.p.v. member_id.
        Schema::table('absences', function (Blueprint $table) {
            $table->foreignUuid('user_id')->nullable()->after('member_id')
                  ->constrained('users')->cascadeOnDelete();
            $table->index(['user_id', 'type']);
        });

        // member_id nullable maken (User-afmeldingen hebben geen member_id).
        Schema::table('absences', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
        });
        Schema::table('absences', function (Blueprint $table) {
            $table->uuid('member_id')->nullable()->change();
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
