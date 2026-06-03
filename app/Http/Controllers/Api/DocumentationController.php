<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Documentation;
use Illuminate\Http\JsonResponse;

class DocumentationController extends Controller
{
    public function index(): JsonResponse
    {
        $sections = Documentation::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'category', 'title', 'body'])
            ->map(fn($d) => [
                'id'       => $d->id,
                'category' => $d->category,
                'title'    => $d->title,
                'body'     => $d->body,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $sections,
        ]);
    }
}
