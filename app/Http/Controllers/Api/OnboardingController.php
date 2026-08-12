<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnboardingSlide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    /**
     * GET /v1/onboarding-slides
     *
     * Kale array van de actieve onboarding-slides van de club van de gebruiker,
     * op volgorde: [{icon, title, body}]. Voor de rondleiding in de app.
     */
    public function index(Request $request): JsonResponse
    {
        $clubId = $request->user()->club_id;

        $slides = OnboardingSlide::query()
            ->where('club_id', $clubId)
            ->activeOrdered()
            ->get()
            ->map(fn (OnboardingSlide $s) => [
                'icon'  => $s->icon ?: 'info',
                'title' => $s->title ?? '',
                'body'  => $s->body ?? '',
            ])
            ->values();

        return response()->json($slides);
    }
}
