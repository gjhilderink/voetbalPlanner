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

        $url = fn (?string $pad) => $pad
            ? \Illuminate\Support\Facades\Storage::disk('logos')->url($pad)
            : '';

        return response()->json([
            'primaryColor'   => $club?->primary_color   ?? '#1e3a5f',
            'secondaryColor' => $club?->secondary_color ?? '#3b82f6',
            'accentColor'    => $club?->accent_color    ?? '#10b981',
            'clubName'       => $club?->name            ?? '',
            'logoPath'       => $club?->logo_path,
            'logoUrl'        => $url($club?->logo_path),
            // Het uiterlijk van de app zelf. Het icoon gaat mee zodat de portal
            // en de app dezelfde bron hebben; wisselen doet de app er nog niet
            // mee (dat kan alleen met iconen die in de build zitten).
            'appIconUrl'     => $url($club?->app_icon_path),
            'splashUrl'      => $url($club?->splash_path),
            'splashBgColor'  => $club?->splash_bg_color ?? '',
        ]);
    }
}
