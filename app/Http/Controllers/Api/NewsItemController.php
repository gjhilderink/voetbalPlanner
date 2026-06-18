<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NewsItemResource;
use App\Models\NewsItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsItemController extends Controller
{
    /**
     * GET /api/v1/news
     *
     * Lijst van gepubliceerde nieuwsitems voor de club van de gebruiker.
     * Optionele filter: ?category=jeugd|senioren|algemeen
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = NewsItem::query()
            ->where('club_id', $user->club_id)
            ->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                  ->orWhere('published_at', '<=', now());
            })
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(
            $items->map(fn ($n) => (new NewsItemResource($n))->resolve())
        );
    }

    /**
     * GET /api/v1/news/{newsItem}
     */
    public function show(Request $request, NewsItem $newsItem): JsonResponse
    {
        if ($newsItem->club_id !== $request->user()->club_id) {
            abort(403, 'Geen toegang.');
        }

        return response()->json(new NewsItemResource($newsItem));
    }
}
