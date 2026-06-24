<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Samenstelling = gekoppelde leden (Member) + gekoppelde accounts (User).
        $members = collect($this->members ?? [])->map(fn($m) => [
            'id'   => $m->id,
            'name' => $m->name,
        ]);
        $users = collect($this->users ?? [])->map(fn($u) => [
            'id'   => $u->id,
            'name' => $u->name,
        ]);
        $all = $members->concat($users)->values()->all();

        return [
            'id'          => $this->id,
            'name'        => $this->name ?? '',
            'description' => $this->description ?? '',
            'teamId'      => $this->team_id ?? '',
            'teamName'    => $this->team?->name ?? '',
            'memberCount' => count($all),
            'members'     => $all,
        ];
    }
}
