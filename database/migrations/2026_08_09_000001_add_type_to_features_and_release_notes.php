<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('features', function (Blueprint $table) {
            // Type van de update: feature | improvement | bugfix.
            $table->string('type')->default('feature')->after('description');
        });

        Schema::table('release_notes', function (Blueprint $table) {
            $table->string('type')->default('feature')->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->dropColumn('type');
        });
        Schema::table('release_notes', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
