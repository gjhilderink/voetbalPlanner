<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Agenda-categorie voor de filterchips in de app. De kleur is de bron van het
 * visuele onderscheid tussen categorieën.
 */
class AgendaCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'slug'  => $this->slug,
            'name'  => $this->name,
            'color' => $this->color,
            'icon'  => $this->icon,
        ];
    }
}
