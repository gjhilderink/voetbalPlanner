<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Models\Lineup;
use App\Models\LineupPlayer;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * De standaardopstelling van een elftal.
 *
 * Een coach zet elke week grotendeels dezelfde ploeg neer. Die basis hoort hij
 * één keer te maken en daarna in te laden, in plaats van hem per wedstrijd
 * opnieuw bij elkaar te slepen.
 *
 * Het antwoord heeft dezelfde vorm als het wedstrijdbord, zodat het scherm in de
 * app hetzelfde kan blijven. Wat er niet in zit: af- en aanmeldingen en
 * vrijgeven. Een sjabloon kent geen afmeldingen, en er is niemand om het aan
 * vrij te geven.
 */
class TeamLineupController extends Controller
{
    /** GET /v1/teams/{team}/default-lineup */
    public function show(Request $request, Team $team): JsonResponse
    {
        $magBeheren = $request->user()?->canManageLineup($team->id) ?? false;

        if (! $magBeheren) {
            return response()->json($this->leegBord(
                'Alleen de coach of leider beheert de standaardopstelling.'
            ));
        }

        $opslag  = $team->default_lineup ?? [];
        $spelers = collect($opslag['players'] ?? []);

        // Namen en rugnummers komen uit de huidige selectie en niet uit de
        // opslag: pas je een rugnummer aan bij Leden, dan klopt het hier ook.
        $leden = $this->ledenVan($team);

        $rij = fn (array $s) => [
            'memberId'   => (string) ($s['member_id'] ?? ''),
            'naam'       => (string) (($leden[$s['member_id'] ?? ''] ?? null)?->name ?? ''),
            'nummer'     => (string) (($leden[$s['member_id'] ?? ''] ?? null)?->shirt_number ?? ''),
            'posX'       => (string) ($s['slot_x'] ?? ''),
            'posY'       => (string) ($s['slot_y'] ?? ''),
            'isAfgemeld' => 'false',
            'period'     => (string) ($s['period'] ?? 1),
        ];

        // Wie intussen weg is bij het elftal valt af; anders staat er een naamloze
        // pion op het veld waar niemand meer bij hoort.
        $bekend = fn (array $s) => isset($leden[$s['member_id'] ?? '']);

        return response()->json([
            'magBeheren'     => 'true',
            // Een sjabloon is altijd zichtbaar voor wie hem mag beheren; het bord
            // gebruikt deze vlag om te bepalen of het iets mag tonen.
            'isVrijgegeven'  => 'true',
            'formation'      => (string) ($opslag['formation'] ?? ''),
            'playersOnField' => (string) ($opslag['players_on_field'] ?? 11),
            'matchFormat'    => (string) ($opslag['match_format'] ?? ''),
            'periods'        => (string) ($opslag['periods'] ?? 2),
            'veld'           => $spelers
                ->filter(fn ($s) => ! ($s['is_substitute'] ?? false))
                ->filter($bekend)->values()->map($rij)->all(),
            'bank'           => $spelers
                ->filter(fn ($s) => (bool) ($s['is_substitute'] ?? false))
                ->filter($bekend)->values()->map($rij)->all(),
            'selectie'       => collect($leden)->map(fn ($m) => [
                'memberId'   => (string) $m->id,
                'naam'       => (string) $m->name,
                'nummer'     => (string) ($m->shirt_number ?? ''),
                'posX'       => '',
                'posY'       => '',
                'isAfgemeld' => 'false',
            ])->values()->all(),
            'wissels'        => [],
            'melding'        => '',
        ]);
    }

    /** POST /v1/teams/{team}/default-lineup */
    public function store(Request $request, Team $team): JsonResponse
    {
        if (! $request->user()?->canManageLineup($team->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Alleen de coach mag de standaardopstelling beheren.',
            ], 403);
        }

        $validated = $request->validate([
            'formation'               => 'nullable|string|max:20',
            'players_on_field'        => 'nullable|integer|min:1|max:11',
            'match_format'            => 'nullable|string|max:40',
            'periods'                 => 'nullable|integer|in:2,4',
            'players'                 => 'present|array',
            'players.*.member_id'     => 'required|uuid|exists:members,id',
            'players.*.is_substitute' => 'nullable|boolean',
            'players.*.slot_x'        => 'nullable|numeric|between:0,1',
            'players.*.slot_y'        => 'nullable|numeric|between:0,1',
            'players.*.period'        => 'nullable|integer|min:1|max:4',
        ]);

        $leden = $this->ledenVan($team);

        // Alleen leden van dit elftal. Beheerrechten op één elftal geven geen
        // toegang tot de rest van de club, ook niet via een sjabloon.
        $spelers = collect($validated['players'])
            ->filter(fn ($s) => isset($leden[$s['member_id']]))
            ->map(fn ($s) => [
                'member_id'     => $s['member_id'],
                'is_substitute' => (bool) ($s['is_substitute'] ?? false),
                'slot_x'        => $s['slot_x'] ?? null,
                'slot_y'        => $s['slot_y'] ?? null,
                // De periode gaat mee. Zou het sjabloon alleen de eerste helft
                // bewaren, dan verdween de tweede bij het inladen zonder dat
                // iemand het merkte.
                'period'        => (int) ($s['period'] ?? 1),
            ])
            ->values()
            ->all();

        $team->update([
            'default_lineup' => [
                'formation'        => $validated['formation'] ?? null,
                'players_on_field' => $validated['players_on_field'] ?? 11,
                'match_format'     => $validated['match_format'] ?? null,
                'periods'          => $validated['periods'] ?? 2,
                'players'          => $spelers,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'De standaardopstelling is opgeslagen.',
        ]);
    }

    /**
     * POST /v1/matches/{match}/lineup/load-default
     *
     * Zet de standaardopstelling van het elftal in deze wedstrijd.
     */
    public function load(Request $request, FootballMatch $match): JsonResponse
    {
        if (! $request->user()?->canManageLineup($match->team_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Alleen de coach mag de opstelling beheren.',
            ], 403);
        }

        $team    = $match->team;
        $opslag  = $team?->default_lineup ?? [];
        $spelers = collect($opslag['players'] ?? []);

        if (! $team || $spelers->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Dit elftal heeft nog geen standaardopstelling.',
            ], 422);
        }

        $leden = $this->ledenVan($team);

        // Afgemelde spelers slaan we over. Ze uit het sjabloon halen zou het
        // sjabloon aantasten voor volgende wedstrijden; hier overslaan raakt
        // alleen deze wedstrijd.
        $afgemeld = \App\Models\Absence::query()
            ->where('type', \App\Models\Absence::TYPE_MATCH)
            ->where('match_id', $match->id)
            ->whereNotNull('member_id')
            ->pluck('member_id')
            ->all();

        $gemist = [];

        DB::transaction(function () use ($match, $opslag, $spelers, $leden, $afgemeld, &$gemist) {
            $lineup = Lineup::updateOrCreate(
                ['match_id' => $match->id],
                [
                    'formation'        => $opslag['formation'] ?? null,
                    'players_on_field' => $opslag['players_on_field'] ?? 11,
                    'match_format'     => $opslag['match_format'] ?? null,
                    'periods'          => $opslag['periods'] ?? 2,
                ],
            );

            // De hele opstelling vervangen en niet aanvullen: "inladen" moet
            // opleveren wat er in het sjabloon staat, niet een mengsel van twee
            // opstellingen waarvan niemand meer weet hoe het zo gekomen is.
            $lineup->players()->delete();

            $volgorde = 0;
            foreach ($spelers as $speler) {
                $id = $speler['member_id'] ?? null;

                if (! $id || ! isset($leden[$id]) || in_array($id, $afgemeld, true)) {
                    // Per speler tellen en niet per rij: wie in twee perioden
                    // stond zou anders als twee overgeslagen spelers gelden.
                    $gemist[$id ?? ''] = true;
                    continue;
                }

                LineupPlayer::create([
                    'lineup_id'     => $lineup->id,
                    'period'        => (int) ($speler['period'] ?? 1),
                    'member_id'     => $id,
                    'position'      => 'player',
                    'shirt_number'  => $leden[$id]->shirt_number,
                    'is_substitute' => (bool) ($speler['is_substitute'] ?? false),
                    'slot_x'        => $speler['slot_x'] ?? null,
                    'slot_y'        => $speler['slot_y'] ?? null,
                    'sort_order'    => $volgorde++,
                ]);
            }
        });

        // Het aantal overgeslagen spelers erbij: een gat in de opstelling dat je
        // niet verwacht is erger dan een gat waarvan je weet dat het er is.
        $melding = 'De standaardopstelling staat klaar.';
        $overgeslagen = count($gemist);

        if ($overgeslagen === 1) {
            $melding .= ' Eén speler is overgeslagen: afgemeld of niet meer bij dit elftal.';
        } elseif ($overgeslagen > 1) {
            $melding .= " {$overgeslagen} spelers zijn overgeslagen: afgemeld of niet meer bij dit elftal.";
        }

        return response()->json(['success' => true, 'message' => $melding]);
    }

    /**
     * De spelende leden van dit elftal, op id.
     *
     * @return array<string, \App\Models\Member>
     */
    private function ledenVan(Team $team): array
    {
        return $team->playingMembers()
            ->where('members.is_active', true)
            ->orderBy('members.name')
            ->get()
            ->keyBy('id')
            ->all();
    }

    /** @return array<string, mixed> */
    private function leegBord(string $melding): array
    {
        return [
            'magBeheren'     => 'false',
            'isVrijgegeven'  => 'false',
            'formation'      => '',
            'playersOnField' => '',
            'matchFormat'    => '',
            'periods'        => '',
            'veld'           => [],
            'bank'           => [],
            'selectie'       => [],
            'wissels'        => [],
            'melding'        => $melding,
        ];
    }
}
