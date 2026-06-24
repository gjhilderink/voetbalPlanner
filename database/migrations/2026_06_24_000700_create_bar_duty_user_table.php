<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Losse User-accounts (zonder lidnummer) kunnen zich ook op een bardienst
        // inschrijven — naast leden (bar_duty_member).
        Schema::create('bar_duty_user', function (Blueprint $table) {
            $table->foreignUuid('bar_duty_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['bar_duty_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bar_duty_user');
    }
};
