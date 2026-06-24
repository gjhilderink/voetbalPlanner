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
        $names       = ($this->members?->pluck('name') ?? collect())
            ->merge($this->users?->pluck('name') ?? collect());
        $memberCount = $names->count();

        $isAssignedToMe =
            ($myMemberId && (bool) $this->members?->contains('id', $myMemberId)) ||
            ($myUserId   && (bool) $this->users?->contains('id', $myUserId));

        // Self-assign mogelijk wanneer:
        //  - bardienst heeft nog plekken vrij
        //  - gebruiker zit nog niet op de bardienst
        //  - gebruiker is als lid/coach of als ouder aan het gekoppelde team
        //    verbonden (geen team = open voor de hele club)
        $canSelfAssign = false;
        if ($user && ! $isAssignedToMe && $memberCount < BarDuty::REQUIRED_MEMBERS) {
            $canSelfAssign = ! $this->team_id
                || $user->accessibleTeams()->contains('id', $this->team_id);
        }

        return [
            'id'           => $this->id,
            'date'         => $this->date?->format('d-m-Y') ?? '',
            'shift'        => $this->shift ?? '',
            'status'       => $this->status ?? '',
            'teamName'     => $this->team?->name ?? '',
            'members'      => $names->join(', '),
            'notes'        => $this->notes ?? '',
            'isAssignedToMe' => $isAssignedToMe,
            'memberCount'    => $memberCount,
            'requiredCount'  => BarDuty::REQUIRED_MEMBERS,
            'canSelfAssign'  => $canSelfAssign,
        ];
    }
}
