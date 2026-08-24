<?php

declare(strict_types=1);

use App\Models\GuardianLink;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/**
 * Rol 'guardian' voor accounts die via een ouder/verzorger-koppeling zijn
 * aangemaakt. Tot nu toe kregen die helemaal geen rol, waardoor ze in het
 * beheer niet van gewone leden te onderscheiden waren.
 *
 * Kent de rol meteen toe aan de ouders die al bestaan, zodat het overzicht ook
 * klopt voor koppelingen van vóór deze wijziging.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['web', 'sanctum'] as $guard) {
            Role::firstOrCreate(['name' => 'guardian', 'guard_name' => $guard]);
        }

        // Bestaande ouders: iedereen die als guardian aan een koppeling hangt.
        $userIds = GuardianLink::query()
            ->with('guardian')
            ->get()
            ->map(fn ($link) => $link->guardian?->user_id)
            ->filter()
            ->unique();

        foreach (User::whereIn('id', $userIds)->get() as $user) {
            if (! $user->hasRole('guardian')) {
                $user->assignRole('guardian');
            }
        }
    }

    public function down(): void
    {
        Role::where('name', 'guardian')->delete();
    }
};
