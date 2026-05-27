<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('club_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignUuid('club_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Create a default club and assign existing data
        $clubId = (string) Str::uuid();

        DB::table('clubs')->insert([
            'id'         => $clubId,
            'name'       => 'Bon Boys',
            'slug'       => 'bon-boys',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('teams')->whereNull('club_id')->update(['club_id' => $clubId]);

        // Assign all non-super_admin users to the default club
        $superAdminRole = DB::table('roles')->where('name', 'super_admin')->first();
        $superAdminUserIds = $superAdminRole
            ? DB::table('model_has_roles')
                ->where('role_id', $superAdminRole->id)
                ->where('model_type', 'App\\Models\\User')
                ->pluck('model_id')
                ->toArray()
            : [];

        DB::table('users')
            ->whereNotIn('id', $superAdminUserIds)
            ->whereNull('club_id')
            ->update(['club_id' => $clubId]);
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['club_id']);
            $table->dropColumn('club_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['club_id']);
            $table->dropColumn('club_id');
        });
    }
};
