<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value', 'label', 'description', 'type', 'group'];

    /**
     * Get a setting value by key with optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) return $default;

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'json'    => json_decode($setting->value, true),
            default   => $setting->value,
        };
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, mixed $value): void
    {
        $encoded = is_array($value) ? json_encode($value) : (string) $value;
        static::where('key', $key)->update(['value' => $encoded]);
    }

    /**
     * Get all settings grouped.
     */
    public static function allGrouped(): array
    {
        return static::all()
            ->groupBy('group')
            ->map(fn($items) => $items->keyBy('key'))
            ->toArray();
    }
}
