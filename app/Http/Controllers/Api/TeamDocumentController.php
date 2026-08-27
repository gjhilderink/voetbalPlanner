<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Documenten bij een elftal, zoals de app ze toont.
 *
 * Beheer gebeurt in de portal; hier alleen lezen.
 */
class TeamDocumentController extends Controller
{
    /** GET /v1/teams/{team}/documents */
    public function index(Request $request, Team $team): JsonResponse
    {
        $mag = $request->user()?->accessibleTeams()->contains('id', $team->id) ?? false;

        if (! $mag) {
            return response()->json([self::leeg('Je hebt geen toegang tot dit elftal.')], 403);
        }

        $documenten = TeamDocument::query()
            ->where('club_id', $team->club_id)
            ->where('is_active', true)
            // Documenten van dit elftal én die van de hele club: de spelregels
            // van de KNVB hangen aan geen enkel elftal maar gaan iedereen aan.
            // Zonder koppeling = clubbreed, dus doesntHave.
            ->where(fn ($q) => $q
                ->whereHas('teams', fn ($t) => $t->where('teams.id', $team->id))
                ->orWhereDoesntHave('teams'))
            ->with('teams:id,name')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        if ($documenten->isEmpty()) {
            return response()->json([self::leeg('Er staan nog geen documenten klaar.')]);
        }

        // Alles als string: de app-struct typeert deze velden zo.
        return response()->json($documenten->map(fn (TeamDocument $d) => [
            'id'          => (string) $d->id,
            'title'       => (string) $d->title,
            'description' => (string) ($d->description ?? ''),
            'url'         => $d->url(),
            'fileName'    => (string) $d->original_name,
            // In hoofdletters, want de app zet hem op een klein vakje: PDF, DOCX.
            'extension'   => strtoupper($d->extension()),
            'sizeLabel'   => $d->sizeLabel(),
            // Waar het bij hoort; bij een clubbreed document zegt dat meer dan
            // een leeg veld. Meerdere elftallen komen door komma's gescheiden.
            'scopeLabel'  => $d->teamsLabel(),
            'dateLabel'   => $d->created_at?->format('d-m-Y') ?? '',
            'melding'     => '',
        ])->values());
    }

    /** @return array<string, string> */
    private static function leeg(string $melding): array
    {
        return [
            'id'          => '',
            'title'       => '',
            'description' => '',
            'url'         => '',
            'fileName'    => '',
            'extension'   => '',
            'sizeLabel'   => '',
            'scopeLabel'  => '',
            'dateLabel'   => '',
            'melding'     => $melding,
        ];
    }
}
