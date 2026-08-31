<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Documentation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Secties voor coaches en leiders blijven weg bij spelers en ouders.
        // Dat is een kwestie van verwarring voorkomen, niet van afscherming:
        // uitleg over een knop die er bij jou niet staat is alleen maar
        // ruis. Geen ingelogde gebruiker (kan niet, de route zit achter auth)
        // wordt behandeld als speler.
        $isStaf = $request->user()?->hasStaffFunction() ?? false;

        $sections = Documentation::query()
            ->where('is_active', true)
            ->when(!$isStaf, fn($q) => $q->where('audience', Documentation::AUDIENCE_ALL))
            ->orderBy('sort_order')
            ->get(['id', 'category', 'title', 'body', 'tour_id', 'tour_start_step'])
            ->map(fn($d) => [
                'id'       => $d->id,
                'category' => $d->category,
                'title'    => $d->title,
                'body'     => $d->body,
                // Leeg betekent: geen rondleiding bij deze sectie. Altijd een
                // string, nooit null: de app-struct zegt String, en null wordt
                // daar de tekst "null" in plaats van leeg.
                'tourId'        => $d->tour_id ?? '',
                // Wel een echt getal: het veld in de app-struct is een geheel
                // getal, want het gaat rechtstreeks een pagina-parameter van
                // dat type in.
                'tourStartStep' => (int) ($d->tour_start_step ?? 0),
            ])->values();

        return response()->json($sections);
    }
}
