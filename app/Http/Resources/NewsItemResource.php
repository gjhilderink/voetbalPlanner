<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\NewsItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $published = $this->published_at ?? $this->created_at;
        $daysOld   = $published?->diffInDays(now()) ?? 0;

        $subtitle = match (true) {
            $daysOld <= 0 => 'Vandaag',
            $daysOld === 1 => '1 dag geleden',
            default        => "$daysOld dagen geleden",
        };

        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'subtitle'      => $subtitle,
            'body'          => $this->body,
            'imageUrl'      => $this->image_path ? asset('storage/' . $this->image_path) : '',
            'category'      => $this->category,
            'categoryLabel' => NewsItem::categoryLabel($this->category),
            'publishedAt'   => $published?->toIso8601String() ?? '',
            'daysOld'       => (int) $daysOld,
        ];
    }
}
