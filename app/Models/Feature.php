<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feature extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'description',
        'status',
        'released_at',
        'sort_order',
    ];

    protected $casts = [
        'released_at' => 'datetime',
        'sort_order'  => 'integer',
    ];

    public const STATUS_RELEASED = 'uitgebracht';

    public static array $statusLabels = [
        'idee'            => 'Idee',
        'gepland'         => 'Gepland',
        'in_ontwikkeling' => 'In ontwikkeling',
        'uitgebracht'     => 'Uitgebracht',
    ];

    public function releaseNotes(): HasMany
    {
        return $this->hasMany(ReleaseNote::class);
    }

    protected static function booted(): void
    {
        // Zet released_at automatisch zodra de feature op "Uitgebracht" gaat.
        static::saving(function (Feature $feature) {
            if ($feature->status === self::STATUS_RELEASED && empty($feature->released_at)) {
                $feature->released_at = now();
            }
        });

        // Genereer automatisch een release note zodra de feature is uitgebracht.
        // firstOrCreate maakt 'm één keer aan (snapshot); daarna kan de super_admin
        // de release note handmatig bijschaven.
        static::saved(function (Feature $feature) {
            if ($feature->status === self::STATUS_RELEASED) {
                ReleaseNote::firstOrCreate(
                    ['feature_id' => $feature->id],
                    [
                        'title'       => $feature->title,
                        'body'        => $feature->description,
                        'released_at' => $feature->released_at ?? now(),
                    ]
                );
            }
        });
    }
}
