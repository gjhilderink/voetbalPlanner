<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'is_encrypted', 'club_id'];

    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean'];
    }

    public static function get(string $key, mixed $default = null, ?string $clubId = null): mixed
    {
        $setting = static::where('key', $key)
            ->where('club_id', $clubId)
            ->first();

        if (!$setting) {
            return $default;
        }

        return $setting->is_encrypted
            ? decrypt($setting->value)
            : $setting->value;
    }

    public static function set(string $key, mixed $value, string $group = 'general', bool $encrypted = false, ?string $clubId = null): self
    {
        return static::updateOrCreate(
            ['key' => $key, 'club_id' => $clubId],
            [
                'value'        => $encrypted ? encrypt($value) : $value,
                'group'        => $group,
                'is_encrypted' => $encrypted,
            ]
        );
    }
}
