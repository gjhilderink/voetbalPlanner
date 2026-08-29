<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een kledingstuk dat de club uitdeelt: shirt, broek, sokken, trainingspak, tas.
 *
 * De maten horen bij het kledingstuk en niet bij de club; zie de migratie.
 */
class ClothingItem extends Model
{
    use HasUuids;

    protected $fillable = ['club_id', 'name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** De maten, in de volgorde waarin ze getoond moeten worden. */
    public function sizes(): HasMany
    {
        return $this->hasMany(ClothingSize::class)->orderBy('sort_order')->orderBy('label');
    }

    public function memberSizes(): HasMany
    {
        return $this->hasMany(MemberClothingSize::class);
    }
}
