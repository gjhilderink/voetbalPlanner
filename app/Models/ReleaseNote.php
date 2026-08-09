<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
