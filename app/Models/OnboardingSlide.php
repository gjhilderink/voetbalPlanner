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
     * Vaste icoon-preset (emoji => label). De app rendert het icoon als een grote
     * emoji-tekst, zodat het per slide dynamisch kan zijn.
     */
    public static array $icons = [
        '👋' => '👋 Welkom',
        '⚽' => '⚽ Voetbal / wedstrijden',
        '🚗' => '🚗 Auto / rijschema',
        '🍺' => '🍺 Bar / bardienst',
        '💬' => '💬 Chat',
        '✅' => '✅ Coach / rechten',
        '👥' => '👥 Team',
        '📅' => '📅 Agenda',
        '🔔' => '🔔 Meldingen',
        '🏆' => '🏆 Beker / prijs',
        '⭐' => '⭐ Ster',
        'ℹ️' => 'ℹ️ Info',
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
