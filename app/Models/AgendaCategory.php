<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Categorie voor de verenigingsagenda (Toernooi, Jeugd, Feest, …), per club
 * beheerbaar zodat clubs hun eigen indeling kunnen voeren.
 */
class AgendaCategory extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Material-iconen die in de app beschikbaar zijn. Bewust een eigen lijst en
     * niet die van OnboardingSlide: die is op onboarding toegesneden.
     */
    public static array $icons = [
        'event'                => 'Agenda',
        'emoji_events'         => 'Beker / toernooi',
        'sports_soccer'        => 'Voetbal',
        'child_care'           => 'Jeugd',
        'volunteer_activism'   => 'Vrijwilligers',
        'groups'               => 'Groep',
        'celebration'          => 'Feest',
        'fitness_center'       => 'Training',
        'apartment'            => 'Vereniging',
        'restaurant'           => 'Eten & drinken',
        'storefront'           => 'Markt / bazaar',
        'more_horiz'           => 'Overig',
    ];

    protected $fillable = [
        'club_id', 'name', 'slug', 'color', 'icon', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function club(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AgendaItem::class);
    }

    public function scopeActiveOrdered($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
