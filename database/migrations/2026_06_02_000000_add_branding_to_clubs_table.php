<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->string('primary_color', 7)->nullable()->after('logo_path');
            $table->string('secondary_color', 7)->nullable()->after('primary_color');
            $table->string('email_header_text', 255)->nullable()->after('secondary_color');
            $table->text('email_intro_text')->nullable()->after('email_header_text');
            $table->string('email_footer_text', 255)->nullable()->after('email_intro_text');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn([
                'primary_color', 'secondary_color',
                'email_header_text', 'email_intro_text', 'email_footer_text',
            ]);
        });
    }
};
