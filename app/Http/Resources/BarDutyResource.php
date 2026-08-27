<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BarDuty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BarDutyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user       = $request->user();
        $member     = $user?->resolveMember();
        $myMemberId = $member?->id;
        $myUserId   = $user?->id;

        // Aangemeld = leden (bar_duty_member) + losse User-accounts (bar_duty_user).
        // Achter de naam het aantal plekken, maar alleen als het er meer dan één
        // is — "Jan (2 personen)" zegt iets, "(1 persoon)" is ruis.
        $naam = fn ($r) => ((int) ($r->pivot->spots ?? 1)) > 1
            ? $r->name . ' (' . (int) $r->pivot->spots . ' personen)'
            : $r->name;

        // Beide kanten eerst naar een gewone array: map() op een lege
        // Eloquent-collectie geeft er weer een Eloquent-collectie terug, en merge()
        // daarop roept getKey() aan op elke waarde — wat op strings stukloopt.
        // Dat gebeurt precies bij een dienst waar wel accounts maar geen leden op
        // staan, en dan valt de hele lijst om met een 500.
        $names = collect($this->members?->map($naam)->all() ?? [])
            ->merge($this->users?->map($naam)->all() ?? []);

        // Telling in plekken, niet in aanmeldingen.
        $memberCount = $this->filledCount();

        $isAssignedToMe =
            ($myMemberId && (bool) $this->members?->contains('id', $myMemberId)) ||
            ($myUserId   && (bool) $this->users?->contains('id', $myUserId));

        // Self-assign mogelijk wanneer:
        //  - bardienst heeft nog plekken vrij
        //  - gebruiker zit nog niet op de bardienst
        //  - gebruiker is als lid/coach of als ouder aan het gekoppelde team
        //    verbonden (geen team = open voor de hele club)
        $required = $this->requiredCount();

        $canSelfAssign = false;
        if ($user && ! $isAssignedToMe && $memberCount < $required) {
            $canSelfAssign = ! $this->team_id
                || $user->accessibleTeams()->contains('id', $this->team_id);
        }

        return [
            'id'           => $this->id,
            'date'         => $this->date?->format('d-m-Y') ?? '',
            // 'shift' is de weergave die de app toont: label + tijden ("Ochtend ·
            // 10:30 - 13:30"). De ruwe sleutel staat in 'shiftKey'.
            'shift'        => trim($this->shiftLabel() . ($this->timeRange() ? ' · ' . $this->timeRange() : '')),
            'shiftKey'     => $this->shift ?? '',
            'shiftLabel'   => $this->shiftLabel(),
            'timeRange'    => $this->timeRange(),
            'startTime'    => $this->startTime(),
            'endTime'      => $this->endTime(),
            'status'       => $this->status ?? '',
            'teamName'     => $this->team?->name ?? '',
            'members'      => $names->join(', '),
            'notes'        => $this->notes ?? '',
            'isAssignedToMe' => $isAssignedToMe,
            'memberCount'    => $memberCount,
            'requiredCount'  => $required,
            'canSelfAssign'  => $canSelfAssign,
            // Hoeveel plekken er nog vrij zijn; de app biedt niet meer aan dan dat.
            'spotsLeft'      => (string) max(0, $required - $memberCount),
        ];
    }
}
