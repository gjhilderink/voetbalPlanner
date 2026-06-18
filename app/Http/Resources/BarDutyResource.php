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
        $member     = $user?->member;
        $myMemberId = $member?->id;

        $memberCount   = $this->members?->count() ?? 0;
        $isAssignedToMe = $myMemberId
            ? (bool) $this->members?->contains('id', $myMemberId)
            : false;

        // Self-assign mogelijk wanneer:
        //  - gebruiker heeft een member-profiel
        //  - bardienst heeft nog plekken vrij
        //  - gebruiker zit nog niet op de bardienst
        //  - gebruiker is lid van het gekoppelde team (geen team = open)
        $canSelfAssign = false;
        if ($member && ! $isAssignedToMe && $memberCount < BarDuty::REQUIRED_MEMBERS) {
            $canSelfAssign = ! $this->team_id
                || $member->teams()->whereKey($this->team_id)->exists();
        }

        return [
            'id'           => $this->id,
            'date'         => $this->date?->format('d-m-Y') ?? '',
            'shift'        => $this->shift ?? '',
            'status'       => $this->status ?? '',
            'teamName'     => $this->team?->name ?? '',
            'members'      => $this->members?->pluck('name')->join(', ') ?? '',
            'notes'        => $this->notes ?? '',
            'isAssignedToMe' => $isAssignedToMe,
            'memberCount'    => $memberCount,
            'requiredCount'  => BarDuty::REQUIRED_MEMBERS,
            'canSelfAssign'  => $canSelfAssign,
        ];
    }
}
