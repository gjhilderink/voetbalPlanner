<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Absence;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Af- en aanmelden namens iemand anders.
 *
 * Een coach ziet langs de lijn wie er wel en niet is, maar kon dat alleen zelf
 * doorgeven per speler die het zelf had gedaan. Met een 'member_id' in het
 * verzoek doet hij het namens een speler — mits hij het elftal beheert en de
 * speler daar ook echt in zit. Zonder 'member_id' verandert er niets: dan geldt
 * het gewoon voor jezelf.
 *
 * Gedeeld door de wedstrijd- en de trainingscontroller, zodat de rechtencheck
 * en de foutmeldingen op één plek staan.
 */
trait ManagesAttendance
{
    /**
     * Voor wie geldt deze af-/aanmelding?
     *
     * @return array{0: ?Member, 1: ?JsonResponse} het lid (null = het losse
     *         account van de gebruiker zelf) en een eventuele foutmelding
     */
    private function attendanceTarget(Request $request, ?string $teamId): array
    {
        $user     = $request->user();
        $memberId = trim((string) $request->input('member_id', ''));

        // Niets meegegeven, of je eigen lidnummer: gewoon jezelf.
        if ($memberId === '') {
            return [$user?->resolveMember(), null];
        }

        if (! $user?->canManageLineup($teamId)) {
            return [null, response()->json([
                'success' => false,
                'message' => 'Alleen de coach of leider kan iemand anders af- of aanmelden.',
            ], 403)];
        }

        $member = Member::find($memberId);

        // Beheerrechten op één elftal geven geen toegang tot de rest van de club.
        if (! $member || ! $teamId || ! $member->teams()->where('teams.id', $teamId)->exists()) {
            return [null, response()->json([
                'success' => false,
                'message' => 'Dit lid hoort niet bij dit elftal.',
            ], 422)];
        }

        return [$member, null];
    }

    /** Doet de gebruiker dit namens een ander? Bepaalt alleen de tekst terug. */
    private function attendanceForSomeoneElse(Request $request): bool
    {
        return trim((string) $request->input('member_id', '')) !== '';
    }

    /**
     * "Je bent afgemeld voor deze training." of "Sterre is afgemeld voor deze
     * training." — zodat de coach ziet dat hij het namens iemand deed.
     */
    private function attendanceMessage(
        Request $request,
        ?Member $member,
        string $werkwoord,
        string $waarvoor,
    ): string {
        if (! $this->attendanceForSomeoneElse($request)) {
            return "Je bent {$werkwoord} voor {$waarvoor}.";
        }

        $naam = $member?->name ?: 'Het lid';

        return "{$naam} is {$werkwoord} voor {$waarvoor}.";
    }

    /**
     * De afwezigheidsrij van dit lid (of, zonder lid, van dit account).
     * Beide controllers bouwen daar dezelfde sleutel voor.
     *
     * @param  array<string, mixed>  $sleutel
     * @return array<string, mixed>
     */
    private function attendanceKey(array $sleutel, ?Member $member, Request $request): array
    {
        return $sleutel + ($member
            ? ['member_id' => $member->id]
            : ['user_id' => $request->user()->id]);
    }
}
