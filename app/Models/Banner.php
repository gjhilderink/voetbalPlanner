<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Banner extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'club_id',
        'title',
        'image_url',
        'link_url',
        'position',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
    ];

    public static array $positionLabels = [
        'global'       => 'Alle pagina\'s',
        'wedstrijden'  => 'Wedstrijden',
        'bardiensten'  => 'Bardiensten',
        'rijschema'    => 'Rijschema',
        'trainingen'   => 'Trainingen',
        'agenda'       => 'Agenda',
        'chat'         => 'Chat',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
