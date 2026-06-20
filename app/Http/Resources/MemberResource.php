<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // hasAppAccount = lid is gekoppeld aan een User (heeft de app
        // geactiveerd). Wordt door de mobile app gebruikt om offline-leden
        // visueel te markeren en chat-pogingen te blokkeren.
        $hasAppAccount = $this->user_id !== null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'role' => $this->role,
            'profile_photo' => $this->profile_photo,
            'is_active' => $this->is_active,
            'external_id' => $this->external_id,
            // CamelCase alias zodat de mobile SwapMember struct (externalId) automatisch matcht.
            'externalId' => $this->external_id,
            'hasAppAccount' => $hasAppAccount,
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
