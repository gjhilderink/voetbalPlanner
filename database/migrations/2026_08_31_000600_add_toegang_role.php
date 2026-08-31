<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rol voor toegangscontrole: beheert de toegangscodes van een activiteit en mag
 * ze in de app scannen bij de ingang.
 *
 * Voor beide guards. De bar_commissie-rol werd ooit alleen voor 'web'
 * aangemaakt, waardoor diezelfde rol in de app (guard sanctum) niet bestond -
 * die fout niet herhalen. Juist hier telt dat: het scannen gebeurt in de app.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['web', 'sanctum'] as $guard) {
            Role::firstOrCreate(['name' => 'toegang', 'guard_name' => $guard]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'toegang')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
