<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * De maat die één lid voor één kledingstuk heeft opgegeven.
 *
 * Eén rij per lid per kledingstuk; de unieke sleutel in de migratie bewaakt dat.
 */
class MemberClothingSize extends Model
{
    use HasUuids;

    protected $fillable = [
        'member_id', 'clothing_item_id', 'clothing_size_id', 'updated_by_user_id',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ClothingItem::class, 'clothing_item_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(ClothingSize::class, 'clothing_size_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
