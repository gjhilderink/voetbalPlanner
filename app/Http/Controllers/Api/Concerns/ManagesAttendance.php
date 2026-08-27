<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Absence;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Voor wie geldt een af- of aanmelding?
 *
 * Drie gevallen, en die zien er voor de app hetzelfde uit — één knop, één
 * verzoek:
 *
 *  - Je hoort zelf bij het elftal: het geldt voor jou.
 *  - Je bent ouder of verzorger: het geldt voor je kind of kinderen in dit
 *    elftal. Dat is wat een ouder bedoelt als hij op Afmelden tikt, en het is
 *    dezelfde regel die de agenda al hanteert.
 *  - Je bent coach en geeft een 'member_id' mee: het geldt voor die speler,
 *    mits die ook echt in dit elftal zit.
 *
 * De server zoekt dat zelf uit in plaats van de app te laten kiezen. Anders zou
 * elk scherm met een af-/aanmeldknop de koppelingen van de gebruiker moeten
 * kennen, en zou een ouder met twee kinderen in hetzelfde elftal overal een
 * keuzemenu krijgen dat hij zelden nodig heeft.
 *
 * Gedeeld door de wedstrijd- en de trainingscontroller, zodat de rechtencheck
 * en de foutmeldingen op één plek staan.
 */
trait ManagesAttendance
{
    /**
     * Voor wie geldt deze af-/aanmelding?
     *
     * @return array{0: array<int, ?Member>, 1: ?JsonResponse} de leden (een
     *         `null` in de lijst betekent: het losse account van de gebruiker
     *         zelf, zonder lidprofiel) en een eventuele foutmelding
     */
    private function attendanceTargets(Request $request, ?string $teamId): array
    {
        $user     = $request->user();
        $memberId = trim((string) $request->input('member_id', ''));
        $eigenId  = $user?->resolveMember()?->id;

        // Niets meegegeven, of je eigen lidnummer: het gaat over jezelf, of —
        // als je hier alleen als ouder komt — over je kinderen in dit elftal.
        if ($memberId === '' || $memberId === $eigenId) {
            if ($user?->belongsToTeam($teamId)) {
                return [[$user->resolveMember()], null];
            }

            $kinderen = $this->attendanceChildren($request, $teamId);

            if ($kinderen->isNotEmpty()) {
                return [$kinderen->all(), null];
            }

            return [[], response()->json([
                'success' => false,
                'message' => 'Je hoort niet bij dit elftal, dus je kunt je hier niet af- of aanmelden.',
            ], 403)];
        }

        // Een ouder mag ook één van zijn kinderen apart noemen.
        $kind = $this->attendanceChildren($request, $teamId)->firstWhere('id', $memberId);
        if ($kind) {
            return [[$kind], null];
        }

        if (! $user?->canManageLineup($teamId)) {
            return [[], response()->json([
                'success' => false,
                'message' => 'Alleen de coach of leider kan iemand anders af- of aanmelden.',
            ], 403)];
        }

        $member = Member::find($memberId);

        // Beheerrechten op één elftal geven geen toegang tot de rest van de club.
        if (! $member || ! $teamId || ! $member->teams()->where('teams.id', $teamId)->exists()) {
            return [[], response()->json([
                'success' => false,
                'message' => 'Dit lid hoort niet bij dit elftal.',
            ], 422)];
        }

        return [[$member], null];
    }

    /**
     * De kinderen van deze gebruiker die in dit elftal spelen.
     *
     * Alleen goedgekeurde koppelingen: een verzoek dat nog openstaat geeft geen
     * enkel recht.
     *
     * @return Collection<int, Member>
     */
    private function attendanceChildren(Request $request, ?string $teamId): Collection
    {
        $eigenId = $request->user()?->resolveMember()?->id;

        if (! $eigenId || ! $teamId) {
            return collect();
        }

        $kindIds = \App\Models\GuardianLink::query()
            ->where('guardian_member_id', $eigenId)
            ->where('status', 'approved')
            ->pluck('child_member_id');

        if ($kindIds->isEmpty()) {
            return collect();
        }

        return Member::query()
            ->whereIn('id', $kindIds)
            ->whereHas('teams', fn ($q) => $q->where('teams.id', $teamId))
            ->orderBy('name')
            ->get();
    }

    /**
     * 'afgemeld' of 'aangemeld', gezien vanuit deze gebruiker.
     *
     * Voor een speler gaat het over hemzelf, voor een ouder over zijn kinderen
     * in dit elftal. Zonder dat onderscheid meldt een ouder zijn kind af en
     * blijft er "aangemeld" staan — en dan meldt hij het nog een keer af.
     *
     * De aanroepers bepalen `$benLid` en `$kindIds` één keer en niet per
     * training: dat scheelt twee queries per regel in de lijst.
     *
     * @param  \Illuminate\Support\Collection<int, Absence>  $absences
     * @param  \Illuminate\Support\Collection<int, string>   $kindIds
     */
    private static function statusVoor(
        Collection $absences,
        bool $benLid,
        ?string $eigenMemberId,
        ?string $eigenUserId,
        Collection $kindIds,
    ): string {
        if ($benLid) {
            $afgemeld = $absences->contains(fn (Absence $a) =>
                ($eigenMemberId && $a->member_id === $eigenMemberId) ||
                ($eigenUserId && $a->user_id === $eigenUserId));

            return $afgemeld ? 'afgemeld' : 'aangemeld';
        }

        if ($kindIds->isEmpty()) {
            return 'aangemeld';
        }

        // Pas 'afgemeld' als het voor al je kinderen in dit elftal geldt. Anders
        // zou één afgemeld kind de knop voor het andere op 'aanmelden' zetten.
        $alle = $kindIds->every(fn (string $id) =>
            $absences->contains(fn (Absence $a) => $a->member_id === $id));

        return $alle ? 'afgemeld' : 'aangemeld';
    }

    /** Doet de gebruiker dit namens een ander? Bepaalt alleen de tekst terug. */
    private function attendanceForSomeoneElse(Request $request, array $leden): bool
    {
        if (trim((string) $request->input('member_id', '')) !== '') {
            return true;
        }

        $eigenId = $request->user()?->resolveMember()?->id;

        // Precies één doel dat jij zelf bent: dan gaat het over jou.
        return ! (count($leden) === 1 && ($leden[0]?->id ?? null) === $eigenId);
    }

    /**
     * "Je bent afgemeld voor deze training." of "Sterre is afgemeld voor deze
     * training." — zodat een ouder of coach ziet dat hij het namens iemand deed.
     *
     * @param  array<int, ?Member>  $leden
     */
    private function attendanceMessage(
        Request $request,
        array $leden,
        string $werkwoord,
        string $waarvoor,
    ): string {
        if (! $this->attendanceForSomeoneElse($request, $leden)) {
            return "Je bent {$werkwoord} voor {$waarvoor}.";
        }

        $namen = collect($leden)->map(fn (?Member $m) => $m?->name)->filter()->values();

        if ($namen->isEmpty()) {
            return "Het lid is {$werkwoord} voor {$waarvoor}.";
        }

        // "Sanne en Sterre zijn afgemeld": een ouder met twee kinderen in
        // hetzelfde elftal moet kunnen zien dat het voor allebei geldt.
        $naam = $namen->count() === 1
            ? $namen->first()
            : $namen->slice(0, -1)->join(', ') . ' en ' . $namen->last();

        $vorm = $namen->count() === 1 ? 'is' : 'zijn';

        return "{$naam} {$vorm} {$werkwoord} voor {$waarvoor}.";
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
