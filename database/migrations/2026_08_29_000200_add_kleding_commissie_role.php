<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rol voor de kledingcommissie: beheert de kledingstukken en maten, en ziet per
 * elftal wie welke maat heeft.
 *
 * Voor beide guards. De bar_commissie-rol werd ooit alleen voor 'web'
 * aangemaakt, waardoor diezelfde rol in de app (guard sanctum) niet bestond -
 * die fout niet herhalen.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['web', 'sanctum'] as $guard) {
            Role::firstOrCreate(['name' => 'kleding_commissie', 'guard_name' => $guard]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'kleding_commissie')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
