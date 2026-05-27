<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'bar_commissie', 'guard_name' => 'web']);
    }

    public function down(): void
    {
        Role::where('name', 'bar_commissie')->delete();
    }
};
