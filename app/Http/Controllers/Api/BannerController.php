<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user     = $request->user()->load('club');
        $clubId   = $user->club_id;
        $position = $request->get('position', 'global');

        $banners = Banner::query()
            ->where('club_id', $clubId)
            ->where('is_active', true)
            ->where(fn($q) => $q->where('position', $position)->orWhere('position', 'global'))
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderByRaw("CASE WHEN position = ? THEN 0 ELSE 1 END", [$position])
            ->get();

        return response()->json(
            $banners->map(fn($b) => (new BannerResource($b))->resolve())
        );
    }
}
