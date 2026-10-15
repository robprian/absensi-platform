<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'value_type', 'group_name', 'description'];

    /**
     * Get a setting value with type coercion. Falls back to $default.
     * Caches only plain arrays (never Eloquent models) to avoid
     * unserialize issues across processes/drivers.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cached = Cache::remember(
            "setting:$key",
            300,
            fn () => static::where('key', $key)->first()?->only(['value', 'value_type'])
        );
        if (!$cached) {
            return $default;
        }
        return match ($cached['value_type']) {
            'integer' => (int) $cached['value'],
            'boolean' => filter_var($cached['value'], FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $cached['value'], true),
            default => $cached['value'],
        };
    }

    public static function set(string $key, mixed $value, string $type = 'string', string $group = 'general'): static
    {
        $stored = $type === 'json' ? json_encode($value) : (string) $value;
        $setting = static::updateOrCreate(['key' => $key], [
            'value' => $stored,
            'value_type' => $type,
            'group_name' => $group,
        ]);
        Cache::forget("setting:$key");
        return $setting;
    }

    public static function isReimbursementEnabled(): bool
    {
        return (bool) static::get('reimbursement_enabled', true);
    }
}
