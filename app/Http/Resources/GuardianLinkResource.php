<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuardianLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statusLabels = [
            'pending'  => 'In afwachting',
            'approved' => 'Goedgekeurd',
            'rejected' => 'Geweigerd',
            'revoked'  => 'Ingetrokken',
        ];

        return [
            'id'              => $this->id,
            'status'          => $this->status,
            'statusLabel'     => $statusLabels[$this->status] ?? $this->status,

            'guardianName'    => $this->guardian?->name ?? '',
            'guardianEmail'   => $this->guardian?->email ?? '',

            'childName'       => $this->child?->name ?? '',
            'childEmail'      => $this->child?->email ?? '',
            'childExternalId' => $this->child?->external_id ?? '',

            'requestedAt'     => $this->created_at?->format('d-m-Y H:i') ?? '',
            'expiresAt'       => $this->expires_at?->format('d-m-Y H:i') ?? '',
            'resolvedAt'      => $this->resolved_at?->format('d-m-Y H:i') ?? '',
            'revokedAt'       => $this->revoked_at?->format('d-m-Y H:i') ?? '',
        ];
    }
}
