<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'profile_photo' => $this->profile_photo,
            'is_active' => $this->is_active,
            'roles' => $this->getRoleNames(),
            'member' => new MemberResource($this->whenLoaded('member')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
