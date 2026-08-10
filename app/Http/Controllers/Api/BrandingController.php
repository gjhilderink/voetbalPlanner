<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('club');
        $club = $user->club;

        return response()->json([
            'primaryColor'   => $club?->primary_color   ?? '#1e3a5f',
            'secondaryColor' => $club?->secondary_color ?? '#3b82f6',
            'accentColor'    => $club?->accent_color    ?? '#10b981',
            'clubName'       => $club?->name            ?? '',
            'logoPath'       => $club?->logo_path,
            'logoUrl'        => $club?->logo_path ? asset('storage/' . $club->logo_path) : '',
        ]);
    }
}
