<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Koppeltabel om losse accounts (User, bv. bardienst-coordinatoren zonder
        // Member) aan een staffgroep te koppelen — naast de leden (Member).
        Schema::create('staff_group_user', function (Blueprint $table) {
            $table->foreignUuid('staff_group_id')->constrained('staff_groups')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['staff_group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_group_user');
    }
};
