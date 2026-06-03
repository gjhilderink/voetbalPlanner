<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swap_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type'); // bardienst | fruitheld | rijden
            $table->uuid('target_id'); // bar_duty id or match id
            $table->uuid('requester_id'); // member who wants to swap out
            $table->uuid('requestee_id'); // member being asked to swap in
            $table->string('status')->default('pending'); // pending | accepted | declined
            $table->string('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swap_requests');
    }
};
