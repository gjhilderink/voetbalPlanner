<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Models\MatchPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Foto's bij een wedstrijd: ophalen, plaatsen en weghalen.
 *
 * Iedereen die bij het elftal hoort mag kijken en plaatsen, met een maximum per
 * persoon. Weghalen mag je bij je eigen foto, en de coach mag alles weghalen —
 * die is aanspreekbaar als er iets tussen staat wat er niet hoort.
 */
class MatchPhotoController extends Controller
{
    private const DISK = 'match_photos';

    /** GET /v1/matches/{match}/photos */
    public function index(Request $request, FootballMatch $match): JsonResponse
    {
        if (! $this->magZien($request, $match)) {
            return response()->json([self::leeg('Je hebt geen toegang tot deze wedstrijd.')], 403);
        }

        return response()->json($this->lijst($request, $match));
    }

    /** POST /v1/matches/{match}/photos */
    public function store(Request $request, FootballMatch $match): JsonResponse
    {
        if (! $this->magZien($request, $match)) {
            return response()->json([
                'success' => false,
                'message' => 'Je hebt geen toegang tot deze wedstrijd.',
            ], 403);
        }

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:8192'],
        ]);

        $user = $request->user();

        $eigen = MatchPhoto::query()
            ->where('match_id', $match->id)
            ->where('user_id', $user->id)
            ->count();

        if ($eigen >= MatchPhoto::MAX_PER_GEBRUIKER) {
            return response()->json([
                'success' => false,
                'message' => 'Je hebt al ' . MatchPhoto::MAX_PER_GEBRUIKER
                    . ' foto\'s bij deze wedstrijd geplaatst. Haal er eerst een weg.',
            ], 422);
        }

        $bestand   = $request->file('photo');
        $extensie  = strtolower($bestand->getClientOriginalExtension() ?: 'jpg');
        $bestandnaam = Str::uuid()->toString() . '.' . $extensie;

        Storage::disk(self::DISK)->put($bestandnaam, file_get_contents($bestand->getRealPath()));

        $foto = MatchPhoto::create([
            'match_id'      => $match->id,
            'user_id'       => $user->id,
            // Momentopname: zie de migratie. De naam van het lid als die er is,
            // anders die van het account.
            'uploader_name' => $user->resolveMember()?->name ?: ($user->name ?: $user->email),
            'path'          => $bestandnaam,
        ]);

        return response()->json([
            'success'   => true,
            'message'   => 'Foto geplaatst.',
            'photoUrl'  => $foto->url(),
            'remaining' => (string) (MatchPhoto::MAX_PER_GEBRUIKER - $eigen - 1),
        ], 201);
    }

    /**
     * POST /v1/matches/{match}/photos/{photo}/delete
     *
     * POST en geen DELETE: de hosting blokkeert die methode, zoals overal in
     * deze API.
     */
    public function destroy(Request $request, FootballMatch $match, MatchPhoto $photo): JsonResponse
    {
        $user = $request->user();

        if ($photo->match_id !== $match->id) {
            return response()->json(['success' => false, 'message' => 'Onbekende foto.'], 404);
        }

        $magWeg = $photo->user_id === $user?->id
            || (bool) $user?->canManageLineup($match->team_id);

        if (! $magWeg) {
            return response()->json([
                'success' => false,
                'message' => 'Je kunt alleen je eigen foto\'s weghalen.',
            ], 403);
        }

        Storage::disk(self::DISK)->delete($photo->path);
        $photo->delete();

        return response()->json(['success' => true, 'message' => 'Foto verwijderd.']);
    }

    private function magZien(Request $request, FootballMatch $match): bool
    {
        return $request->user()?->accessibleTeams()->contains('id', $match->team_id) ?? false;
    }

    /**
     * De lijst zoals de app hem verwacht: platte regels, alles als string.
     *
     * canDelete rekent de server uit. Zou de app dat zelf doen, dan had hij de
     * rol per elftal nodig en zouden de knoppen alsnog niet kloppen zodra iemand
     * coach van één team is en speler van een ander.
     *
     * @return array<int, array<string, string>>
     */
    private function lijst(Request $request, FootballMatch $match): array
    {
        $user   = $request->user();
        $isCoach = (bool) $user?->canManageLineup($match->team_id);

        $fotos = MatchPhoto::query()
            ->where('match_id', $match->id)
            ->orderByDesc('created_at')
            ->get();

        $eigen = $fotos->where('user_id', $user?->id)->count();
        $over  = max(0, MatchPhoto::MAX_PER_GEBRUIKER - $eigen);

        if ($fotos->isEmpty()) {
            return [self::leeg('Nog geen foto\'s bij deze wedstrijd.', (string) $over)];
        }

        return $fotos->map(fn (MatchPhoto $f) => [
            'id'           => (string) $f->id,
            'url'          => $f->url(),
            'uploaderName' => (string) $f->uploader_name,
            'dateLabel'    => $f->created_at?->format('d-m-Y H:i') ?? '',
            'canDelete'    => ($f->user_id === $user?->id || $isCoach) ? 'true' : 'false',
            // Op elke regel mee, zodat de app het aantal kan tonen zonder de
            // lijst zelf te hoeven tellen en filteren.
            'remaining'    => (string) $over,
            'melding'      => '',
        ])->values()->all();
    }

    /** @return array<string, string> */
    private static function leeg(string $melding, string $over = '0'): array
    {
        return [
            'id'           => '',
            'url'          => '',
            'uploaderName' => '',
            'dateLabel'    => '',
            'canDelete'    => 'false',
            'remaining'    => $over,
            'melding'      => $melding,
        ];
    }
}
