<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SwapRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $typeLabels = [
            'bardienst' => 'Bardienst',
            'fruitheld' => 'Fruitheld',
            'rijden'    => 'Rijden',
        ];

        return [
            'id'                 => $this->id,
            'type'               => $this->type,
            'typeLabel'          => $typeLabels[$this->type] ?? $this->type,
            'targetId'           => $this->target_id,
            'targetDescription'  => $this->targetDescription(),
            'requesterName'      => $this->requester?->name ?? '',
            'requesteeName'      => $this->requestee?->name ?? '',
            'status'             => $this->status,
            'message'            => $this->message ?? '',
            'date'               => $this->created_at?->format('d-m-Y') ?? '',
        ];
    }

    private function targetDescription(): string
    {
        return match ($this->type) {
            'bardienst' => $this->resolveBarDutyDescription(),
            'fruitheld' => $this->resolveMatchDescription('fruitheld'),
            'rijden'    => $this->resolveMatchDescription('rijden'),
            default     => '',
        };
    }

    private function resolveBarDutyDescription(): string
    {
        $duty = \App\Models\BarDuty::find($this->target_id);
        if (!$duty) return '';
        return 'Bardienst ' . ($duty->date?->format('d-m-Y') ?? '') . ' ' . $duty->shift;
    }

    private function resolveMatchDescription(string $role): string
    {
        $match = \App\Models\FootballMatch::find($this->target_id);
        if (!$match) return '';
        $roleLabel = $role === 'fruitheld' ? 'Fruitheld' : 'Rijden';
        return $roleLabel . ': ' . ($match->opponent ?? '') . ' (' . ($match->match_datetime?->format('d-m-Y') ?? '') . ')';
    }
}
