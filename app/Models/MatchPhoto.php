<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén foto bij een wedstrijd.
 */
class MatchPhoto extends Model
{
    use HasUuids;

    /** Hoeveel foto's één gebruiker per wedstrijd mag plaatsen. */
    public const MAX_PER_GEBRUIKER = 5;

    protected $fillable = [
        'match_id', 'user_id', 'uploader_name', 'path',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * De volledige URL.
     *
     * Opgeslagen wordt alleen het pad; de disk schrijft rechtstreeks in public/
     * omdat de storage-symlink op deze hosting niet gegarandeerd is. Zelfde keuze
     * als bij de clublogo's en de pasfoto's.
     */
    public function url(): string
    {
        return $this->path ? asset('match_photos/' . $this->path) : '';
    }
}
