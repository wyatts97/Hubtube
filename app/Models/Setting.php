<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = Cache::remember("setting.{$key}", 3600, function () use ($key) {
            return static::where('key', $key)->first();
        });

        if (!$setting) {
            return $default;
        }

        return static::castValue($setting->value, $setting->type);
    }

    public static function set(string $key, mixed $value, string $group = 'general', string $type = 'string'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : $value,
                'group' => $group,
                'type' => $type,
            ]
        );

        Cache::forget("setting.{$key}");
    }

    protected static function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            'array', 'json' => json_decode($value, true) ?? [],
            default => $value,
        };
    }

    public static function getGroup(string $group): array
    {
        return static::where('group', $group)
            ->get()
            ->mapWithKeys(fn ($setting) => [
                $setting->key => static::castValue($setting->value, $setting->type)
            ])
            ->toArray();
    }

    public static function getPublic(): array
    {
        return static::where('is_public', true)
            ->get()
            ->mapWithKeys(fn ($setting) => [
                $setting->key => static::castValue($setting->value, $setting->type)
            ])
            ->toArray();
    }
}
