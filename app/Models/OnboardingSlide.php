<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén onboarding-slide van een club (rondleiding bij de eerste keer inloggen).
 */
class OnboardingSlide extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'club_id',
        'title',
        'body',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Vaste icoon-preset (Material-iconnaam => label). Dezelfde namen kent de
     * app (custom widget DynamicMaterialIcon); houd beide in sync.
     */
    public static array $icons = [
        'waving_hand'     => 'Zwaaiende hand (welkom)',
        'sports_soccer'   => 'Voetbal (wedstrijden)',
        'directions_car'  => 'Auto (rijschema)',
        'sports_bar'      => 'Bar (bardienst)',
        'chat'            => 'Chat',
        'verified_user'   => 'Coach / rechten',
        'groups'          => 'Team',
        'calendar_today'  => 'Agenda',
        'notifications'   => 'Meldingen',
        'emoji_events'    => 'Beker / prijs',
        'star'            => 'Ster',
        'info'            => 'Info',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** Actieve slides op volgorde. */
    public function scopeActiveOrdered($query)
    {
        return $query->where('is_active', true)
                     ->orderBy('sort_order')
                     ->orderBy('created_at');
    }
}
