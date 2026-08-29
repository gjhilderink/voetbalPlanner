<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Eén maat bij één kledingstuk: 'M', maar ook '41-46' of 'One size'. */
class ClothingSize extends Model
{
    use HasUuids;

    protected $fillable = ['clothing_item_id', 'label', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ClothingItem::class, 'clothing_item_id');
    }

    public function memberSizes(): HasMany
    {
        return $this->hasMany(MemberClothingSize::class);
    }

    /** Is deze maat ergens gekozen? Bepaalt of hij nog weg mag. */
    public function inGebruik(): bool
    {
        return $this->memberSizes()->exists();
    }
}
