<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('club_name');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone')->nullable();

            // Bij benadering; het is de vraag waar de tarieven aan hangen, dus
            // handig om te weten vóór het gesprek. Vrijblijvend, dus optioneel.
            $table->unsignedInteger('member_count')->nullable();

            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending'); // pending|scheduled|completed|cancelled
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
