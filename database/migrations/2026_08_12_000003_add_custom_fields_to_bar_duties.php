<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Handmatige bardiensten: naast de vaste za/zo-dagdelen kan een club nu ook
 * op andere dagen/tijden een bardienst inplannen (shift = 'custom'), met een
 * eigen label, start-/eindtijd en benodigde bezetting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bar_duties', function (Blueprint $table) {
            $table->string('custom_label')->nullable()->after('shift');
            $table->string('start_time', 5)->nullable()->after('custom_label');
            $table->string('end_time', 5)->nullable()->after('start_time');
            $table->unsignedTinyInteger('required_count')->nullable()->after('end_time');
        });
    }

    public function down(): void
    {
        Schema::table('bar_duties', function (Blueprint $table) {
            $table->dropColumn(['custom_label', 'start_time', 'end_time', 'required_count']);
        });
    }
};
