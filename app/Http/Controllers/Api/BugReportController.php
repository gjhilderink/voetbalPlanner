<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BugReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BugReportController extends Controller
{
    /**
     * POST /api/v1/bug-reports
     *
     * Multipart/form-data velden:
     *   title           (required, string max 200)
     *   description     (required, string max 5000)
     *   app_version     (optional, string)
     *   platform        (optional, string — 'android'|'ios'|'web')
     *   device_info     (optional, string)
     *   screenshots[]   (optional, image array max 5, each max 5MB)
     *
     * Auth: Bearer token vereist. Throttled (5/min).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'title'           => 'required|string|max:200',
            'description'     => 'required|string|max:5000',
            'app_version'     => 'nullable|string|max:50',
            'platform'        => 'nullable|string|max:50',
            'device_info'     => 'nullable|string|max:255',
            'screenshots'     => 'nullable|array|max:5',
            'screenshots.*'   => 'image|mimes:jpeg,png,webp|max:5120',
        ]);

        $paths = [];
        if ($request->hasFile('screenshots')) {
            foreach ($request->file('screenshots') as $file) {
                $paths[] = $file->store('bug_screenshots', 'public');
            }
        }

        $report = BugReport::create([
            'user_id'          => $user->id,
            'club_id'          => $user->club_id,
            'title'            => $validated['title'],
            'description'      => $validated['description'],
            'app_version'      => $validated['app_version'] ?? null,
            'platform'         => $validated['platform'] ?? null,
            'device_info'      => $validated['device_info'] ?? null,
            'screenshot_paths' => $paths,
            'status'           => 'open',
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'    => $report->id,
                'title' => $report->title,
            ],
            'message' => 'Bedankt voor je melding! We bekijken hem zo snel mogelijk.',
        ], 201);
    }
}
