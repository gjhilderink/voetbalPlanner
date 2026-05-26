<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'is_encrypted'];

    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) {
            return $default;
        }
        return $setting->is_encrypted
            ? decrypt($setting->value)
            : $setting->value;
    }

    public static function set(string $key, mixed $value, string $group = 'general', bool $encrypted = false): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $encrypted ? encrypt($value) : $value,
                'group' => $group,
                'is_encrypted' => $encrypted,
            ]
        );
    }
}
