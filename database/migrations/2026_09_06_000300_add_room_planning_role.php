<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * De rol Ruimteplanning.
 *
 * Wie hem heeft mag ruimtes beheren en reserveren - in de portal en in de app.
 * Los van coach of bestuur: degene die de kantine inplant is vaak geen van
 * beide.
 *
 * Voor allebei de guards. De portal werkt op 'web' en de app op 'sanctum'; een
 * rol die maar aan één kant bestaat werkt op de andere stilzwijgend niet, en dat
 * is bij bar_commissie ooit misgegaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['web', 'sanctum'] as $guard) {
            Role::firstOrCreate(['name' => 'room-planning', 'guard_name' => $guard]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'room-planning')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
