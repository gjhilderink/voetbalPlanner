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
     * Upload en sla een profielfoto op voor het gekoppelde lid.
     * Accepteert multipart/form-data met een 'photo' veld (max 5 MB, jpeg/png/webp).
     */
    public function updatePhoto(Request $request): JsonResponse
    {
        $member = $request->user()->member;

        if (! $member) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Geen lid-profiel gevonden voor uw account.',
            ], 422);
        }

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ]);

        // Verwijder de oude foto als die bestaat
        if ($member->profile_photo && Storage::disk('public')->exists($member->profile_photo)) {
            Storage::disk('public')->delete($member->profile_photo);
        }

        $path = $request->file('photo')->store('profile_photos', 'public');

        $member->update(['profile_photo' => $path]);

        return response()->json([
            'success' => true,
            'data'    => [
                'profile_photo_url' => asset('storage/' . $path),
            ],
            'message' => 'Profielfoto bijgewerkt.',
        ]);
    }
}
