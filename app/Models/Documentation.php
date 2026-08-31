<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Documentation extends Model
{
    use HasUuids;

    public const AUDIENCE_ALL   = 'all';
    public const AUDIENCE_STAFF = 'staff';

    /** Voor wie een sectie bedoeld is. */
    public static array $audienceLabels = [
        self::AUDIENCE_ALL   => 'Iedereen',
        self::AUDIENCE_STAFF => 'Alleen coaches en leiders',
    ];

    protected $fillable = [
        'category',
        'audience',
        'title',
        'body',
        'sort_order',
        'is_active',
        'tour_id',
        'tour_start_step',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'sort_order'      => 'integer',
        'tour_start_step' => 'integer',
    ];

    /**
     * De rondleidingen die de app kent, als sleutel => label.
     *
     * Staat hier en niet in de database: de rondleidingen zelf zitten in de
     * app-code (TourDefinities). Komt er een bij, dan hoort deze lijst mee te
     * groeien - en de knop in de handleiding-pagina van de app ook.
     */
    public static array $tourLabels = [
        'wedstrijd_afgelasten'  => 'Een wedstrijd afgelasten',
        'gastspeler_uitnodigen' => 'Een gastspeler uitnodigen',
    ];

    public static array $categoryLabels = [
        'app'         => 'De App',
        'platform'    => 'Het Platform',
        'koppelingen' => 'Koppelingen',
    ];

    public function getCategoryLabelAttribute(): string
    {
        return self::$categoryLabels[$this->category] ?? ucfirst($this->category);
    }
}
