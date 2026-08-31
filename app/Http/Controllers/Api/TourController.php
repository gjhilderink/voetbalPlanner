<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Waar moet een in-app rondleiding beginnen?
 *
 * De rondleidingen wijzen de echte schermen aan en niet een nagebouwd
 * voorbeeld. Daarvoor is een echte wedstrijd nodig, en die moet de server
 * uitzoeken: alleen daar staat wie waar coach of leider van is.
 *
 * Is er niets bruikbaars, dan komt er een lege matchId terug plus een zin die
 * de app kan tonen. Bewust geen 404: "er is nu geen wedstrijd" is geen fout,
 * het is een antwoord.
 */
class TourController extends Controller
{
    /** Rondleidingen die een wedstrijd nodig hebben. */
    private const WEDSTRIJD_TOURS = [
        'wedstrijd_afgelasten',
        'gastspeler_uitnodigen',
    ];

    public function target(Request $request): JsonResponse
    {
        $tour = (string) $request->query('tour', '');
        $user = $request->user();

        if (! in_array($tour, self::WEDSTRIJD_TOURS, true)) {
            return $this->leeg('Deze uitleg heeft geen scherm om aan te wijzen.');
        }

        // Beide rondleidingen laten coach-knoppen zien. Zonder staffunctie
        // staan die knoppen er niet, en dan wijst de rondleiding naar niets.
        if (! $user?->hasStaffFunction()) {
            return $this->leeg(
                'Deze uitleg gaat over knoppen die alleen een coach of leider ziet.'
            );
        }

        $teamIds = $this->beheerdeTeamIds($user);
        if ($teamIds->isEmpty()) {
            return $this->leeg('Je bent nog niet aan een elftal gekoppeld.');
        }

        // De eerstvolgende wedstrijd. Niet een gespeelde: de knoppen om af te
        // gelasten of een gastspeler uit te nodigen horen bij wat nog komt.
        $match = FootballMatch::query()
            ->whereIn('team_id', $teamIds)
            ->where('match_datetime', '>=', now())
            ->orderBy('match_datetime')
            ->first(['id']);

        if (! $match) {
            return $this->leeg('Er staat op dit moment geen wedstrijd gepland.');
        }

        return response()->json([
            'matchId' => $match->id,
            'message' => '',
        ]);
    }

    /**
     * De elftallen waarvan deze gebruiker de wedstrijden mag beheren.
     *
     * Coach en leider, en niet assistent: hasStaffFunction() bepaalt of je de
     * uitleg te zien krijgt, maar de knoppen zelf hangen aan
     * Member::MANAGEMENT_ROLES. Zou ik hier ruimer zijn, dan opende de
     * rondleiding een wedstrijd waar de aangewezen knoppen ontbreken.
     */
    private function beheerdeTeamIds($user): \Illuminate\Support\Collection
    {
        if ($user->hasAnyRole(['super_admin', 'club_admin'])) {
            return $user->accessibleTeams()->pluck('id');
        }

        $mgmt = Member::MANAGEMENT_ROLES;

        $viaUser = $user->managedTeams()
            ->wherePivotIn('role', $mgmt)
            ->pluck('teams.id');

        $viaLid = $user->resolveMember()
            ?->teams()
            ->wherePivotIn('role', $mgmt)
            ->pluck('teams.id')
            ?? collect();

        return $viaUser->merge($viaLid)->unique()->values();
    }

    private function leeg(string $melding): JsonResponse
    {
        return response()->json([
            'matchId' => '',
            'message' => $melding,
        ]);
    }
}
