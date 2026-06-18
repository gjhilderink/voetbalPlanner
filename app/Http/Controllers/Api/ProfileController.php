<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * PATCH /api/v1/profile/photo
     *
     * Upload en sla een profielfoto op voor de ingelogde gebruiker. Werkt voor
     * zowel leden als beheerders/admins; het pad wordt altijd op users.profile_photo
     * geschreven en (indien aanwezig) ook gesynchroniseerd naar members.profile_photo
     * zodat oude code die uit members leest blijft werken.
     * Accepteert multipart/form-data met een 'photo' veld (max 5 MB, jpeg/png/webp).
     */
    public function updatePhoto(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Niet geauthenticeerd.',
            ], 401);
        }

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ]);

        $member = $user->member;

        // Verwijder oude foto's (zowel user als member) als die bestaan.
        $disk = Storage::disk('public');
        if ($user->profile_photo && $disk->exists($user->profile_photo)) {
            $disk->delete($user->profile_photo);
        }
        if ($member && $member->profile_photo
            && $member->profile_photo !== $user->profile_photo
            && $disk->exists($member->profile_photo)) {
            $disk->delete($member->profile_photo);
        }

        $path = $request->file('photo')->store('profile_photos', 'public');

        $user->update(['profile_photo' => $path]);
        if ($member) {
            $member->update(['profile_photo' => $path]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'profile_photo_url' => asset('storage/' . $path),
            ],
            'message' => 'Profielfoto bijgewerkt.',
        ]);
    }
}
