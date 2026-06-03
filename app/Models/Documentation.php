<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Documentation extends Model
{
    use HasUuids;

    protected $fillable = [
        'category',
        'title',
        'body',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
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
