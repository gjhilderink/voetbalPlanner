<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hoeveel plekken één aanmelding vult.
 *
 * Ouders komen vaak met z'n tweeën en melden zich via één account aan. Tot nu
 * toe telde elke aanmelding voor precies één plek, waardoor de bardienst open
 * bleef staan terwijl er al genoeg mensen waren.
 *
 * Standaard 1, dus bestaande aanmeldingen blijven tellen zoals ze deden.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['bar_duty_member', 'bar_duty_user'] as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'spots')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedTinyInteger('spots')->default(1);
            });
        }
    }

    public function down(): void
    {
        foreach (['bar_duty_member', 'bar_duty_user'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'spots')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('spots');
            });
        }
    }
};
