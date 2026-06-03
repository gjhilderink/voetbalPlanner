<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('club_name');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('sportlink_username');
            $table->text('sportlink_password'); // stored encrypted
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending'); // pending|approved|rejected
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_requests');
    }
};
