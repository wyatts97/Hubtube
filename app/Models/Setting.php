<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

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

    protected static string $cachePrefix = 'settings:';
    protected static int $cacheTtl = 86400; // 24 hours

    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = static::$cachePrefix . $key;
        
        // Try to get from cache first
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Query database
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            // Cache null values to prevent repeated DB queries
            Cache::put($cacheKey, $default, static::$cacheTtl);
            return $default;
        }

        $value = static::castValue($setting->value, $setting->type);
        Cache::put($cacheKey, $value, static::$cacheTtl);

        return $value;
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

        // Clear specific key cache
        Cache::forget(static::$cachePrefix . $key);
        // Clear group cache
        Cache::forget(static::$cachePrefix . 'group:' . $group);
        // Clear all settings cache
        Cache::forget(static::$cachePrefix . 'all');
        Cache::forget(static::$cachePrefix . 'public');
    }

    /**
     * Store a setting value encrypted at rest.
     * Use for sensitive credentials (SMTP passwords, API keys, secret keys).
     */
    public static function setEncrypted(string $key, mixed $value, string $group = 'general'): void
    {
        $encrypted = (!empty($value) && is_string($value)) ? Crypt::encryptString($value) : $value;

        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $encrypted,
                'group' => $group,
                'type' => 'encrypted',
            ]
        );

        Cache::forget(static::$cachePrefix . $key);
        Cache::forget(static::$cachePrefix . 'group:' . $group);
        Cache::forget(static::$cachePrefix . 'all');
        Cache::forget(static::$cachePrefix . 'public');
    }

    /**
     * Retrieve a setting that was stored encrypted.
     * Transparently decrypts the value. Returns $default if key not found or decryption fails.
     */
    public static function getDecrypted(string $key, mixed $default = ''): string
    {
        $setting = static::where('key', $key)->first();

        if (!$setting || empty($setting->value)) {
            return $default;
        }

        if ($setting->type === 'encrypted') {
            try {
                return Crypt::decryptString($setting->value);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // Value may have been stored before encryption was added — return raw
                return (string) $setting->value;
            }
        }

        // Backwards-compatible: return raw value if not yet encrypted
        return (string) $setting->value;
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
        $cacheKey = static::$cachePrefix . 'group:' . $group;

        return Cache::remember($cacheKey, static::$cacheTtl, function () use ($group) {
            return static::where('group', $group)
                ->get()
                ->mapWithKeys(fn ($setting) => [
                    $setting->key => static::castValue($setting->value, $setting->type)
                ])
                ->toArray();
        });
    }

    public static function getPublic(): array
    {
        $cacheKey = static::$cachePrefix . 'public';

        return Cache::remember($cacheKey, static::$cacheTtl, function () {
            return static::where('is_public', true)
                ->get()
                ->mapWithKeys(fn ($setting) => [
                    $setting->key => static::castValue($setting->value, $setting->type)
                ])
                ->toArray();
        });
    }

    public static function getAll(): array
    {
        $cacheKey = static::$cachePrefix . 'all';

        return Cache::remember($cacheKey, static::$cacheTtl, function () {
            return static::all()
                ->mapWithKeys(fn ($setting) => [
                    $setting->key => static::castValue($setting->value, $setting->type)
                ])
                ->toArray();
        });
    }

    public static function clearCache(): void
    {
        // Clear all settings-related cache keys
        $keys = static::pluck('key')->toArray();
        foreach ($keys as $key) {
            Cache::forget(static::$cachePrefix . $key);
        }
        
        $groups = static::distinct()->pluck('group')->toArray();
        foreach ($groups as $group) {
            Cache::forget(static::$cachePrefix . 'group:' . $group);
        }
        
        Cache::forget(static::$cachePrefix . 'all');
        Cache::forget(static::$cachePrefix . 'public');
    }
}
