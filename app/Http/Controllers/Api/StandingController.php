<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\StandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * De poulestand van een elftal. Het ophalen en omzetten zit in StandingService,
 * omdat het dashboard dezelfde gegevens gebruikt.
 */
class StandingController extends Controller
{
    public function __construct(private readonly StandingService $standen)
    {
    }

    /** GET /v1/teams/{team}/standing */
    public function show(Request $request, Team $team): JsonResponse
    {
        // Alleen elftallen waar je bij hoort; een stand is niet geheim, maar er
        // is ook geen reden om de hele competitie van een vreemde club te openen.
        $mag = $request->user()?->accessibleTeams()->contains('id', $team->id) ?? false;
        if (! $mag) {
            return response()->json(self::alleenMelding('Je hebt geen toegang tot dit elftal.'), 403);
        }

        $stand = $this->standen->forTeam($team);

        if (! $stand['rijen']) {
            return response()->json(self::alleenMelding($stand['melding']));
        }

        // Een platte array, zoals elk ander lijst-endpoint hier. De melding gaat
        // op elke regel mee, want een structlijst uit een JSON-pad trekken is in
        // FlutterFlow onnodig gedoe.
        return response()->json(
            array_map(fn (array $r) => $r + ['melding' => ''], $stand['rijen'])
        );
    }

    /**
     * Eén lege regel die alleen een melding draagt; de app leest die van de
     * eerste regel, net als bij de opkomstlijsten.
     *
     * @return array<int, array<string, string>>
     */
    private static function alleenMelding(string $melding): array
    {
        return [[
            'positie'     => '',
            'team'        => '',
            'logo'        => '',
            'gespeeld'    => '',
            'gewonnen'    => '',
            'gelijk'      => '',
            'verloren'    => '',
            'punten'      => '',
            'doelsaldo'   => '',
            'voor'        => '',
            'tegen'       => '',
            'isEigenTeam' => 'false',
            'melding'     => $melding,
        ]];
    }
}
