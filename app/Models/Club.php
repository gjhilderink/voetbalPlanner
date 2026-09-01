<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Club extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name', 'slug', 'logo_path', 'address', 'city',
        'phone', 'email', 'website', 'is_active',
        'primary_color', 'secondary_color', 'accent_color',
        'email_header_text', 'email_intro_text', 'email_footer_text', 'email_subject',
        'app_icon_path', 'splash_path', 'splash_bg_color',
        'access_enabled',
        'ticketshop_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'access_enabled'     => 'boolean',
            'ticketshop_enabled' => 'boolean',
        ];
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function teamIds(): Collection
    {
        return $this->teams()->pluck('id');
    }
}
