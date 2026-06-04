<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'title'    => $this->title,
            'imageUrl' => $this->image_url,
            'linkUrl'  => $this->link_url ?? '',
            'position' => $this->position,
        ];
    }
}
