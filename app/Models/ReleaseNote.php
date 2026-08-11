<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReleaseNote extends Model
{
    use HasUuids;

    protected $fillable = [
        'feature_id',
        'type',
        'title',
        'body',
        'released_at',
    ];

    /** Type van de update (voor label + gekleurde badge). */
    public static array $typeLabels = [
        'feature'     => 'Nieuwe functie',
        'improvement' => 'Verbetering',
        'bugfix'      => 'Bugfix',
    ];

    /** Badge-kleur per type (Filament-kleuren). */
    public static array $typeColors = [
        'feature'     => 'success',
        'improvement' => 'info',
        'bugfix'      => 'warning',
    ];

    protected $casts = [
        'released_at' => 'datetime',
    ];

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /** Verzendlog: alle mails die voor deze release note zijn verstuurd. */
    public function sends(): HasMany
    {
        return $this->hasMany(ReleaseNoteSend::class);
    }

    /**
     * "Echt" verzonden = minstens één geslaagde mail naar een selectie of naar
     * iedereen (een testmail naar de beheerder zelf telt niet mee).
     */
    public function wasSent(): bool
    {
        return $this->sends()
            ->where('status', 'sent')
            ->whereIn('scope', ['selected', 'all'])
            ->exists();
    }

    /** Tijdstip van de laatste geslaagde (niet-test) verzending, of null. */
    public function lastSentAt(): ?\Illuminate\Support\Carbon
    {
        $max = $this->sends()
            ->where('status', 'sent')
            ->whereIn('scope', ['selected', 'all'])
            ->max('sent_at');

        return $max ? \Illuminate\Support\Carbon::parse($max) : null;
    }
}
