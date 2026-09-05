<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Eén ruimte, zoals de app hem toont.
 *
 * Alles als string, net als bij de agenda: de structs in de app declareren hun
 * velden zo, en een getal of een boolean komt daar anders als null binnen.
 *
 * @mixin Room
 */
class RoomResource extends JsonResource
{
    /** @return array<string, string> */
    public function toArray(Request $request): array
    {
        return [
            'id'          => (string) $this->id,
            'name'        => (string) $this->name,
            'description' => (string) ($this->description ?? ''),
            // Leeg als er niets is ingevuld; de app toont dan geen regel in
            // plaats van "0 personen".
            'capacity'      => $this->capacity ? (string) $this->capacity : '',
            'capacityLabel' => $this->capacity ? $this->capacity . ' personen' : '',
            'color'         => $this->kleur(),
            'melding'       => '',
        ];
    }
}
