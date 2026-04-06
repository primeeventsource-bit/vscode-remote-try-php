<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $fillable = [
        'category',
        'key',
        'value',
        'updated_by',
    ];

    // ── Static Accessors ───────────────────────────

    /**
     * Get a setting value. Falls back to crm_settings for backward compat.
     */
    public static function getValue(string $category, string $key, mixed $default = null): mixed
    {
        $cacheKey = "app_setting.{$category}.{$key}";

        return Cache::remember($cacheKey, 300, function () use ($category, $key, $default) {
            try {
                $row = static::where('category', $category)->where('key', $key)->first();
                if ($row) {
                    $decoded = json_decode($row->value, true);
                    return $decoded !== null ? $decoded : $row->value;
                }

                // Fallback to crm_settings for backward compat
                $legacy = \Illuminate\Support\Facades\DB::table('crm_settings')
                    ->where('key', "{$category}.{$key}")
                    ->value('value');

                if ($legacy !== null) {
                    $decoded = json_decode($legacy, true);
                    return $decoded !== null ? $decoded : $legacy;
                }

                return $default;
            } catch (\Throwable $e) {
                return $default;
            }
        });
    }

    /**
     * Set a setting value.
     */
    public static function setValue(string $category, string $key, mixed $value, ?int $userId = null): void
    {
        $encoded = is_string($value) ? $value : json_encode($value);

        static::updateOrCreate(
            ['category' => $category, 'key' => $key],
            ['value' => $encoded, 'updated_by' => $userId]
        );

        // Also update legacy crm_settings for backward compat
        try {
            \Illuminate\Support\Facades\DB::table('crm_settings')->updateOrInsert(
                ['key' => "{$category}.{$key}"],
                ['value' => $encoded]
            );
        } catch (\Throwable $e) {}

        Cache::forget("app_setting.{$category}.{$key}");
    }

    /**
     * Get all settings for a category.
     */
    public static function getCategory(string $category): array
    {
        try {
            return static::where('category', $category)
                ->pluck('value', 'key')
                ->map(function ($v) {
                    $decoded = json_decode($v, true);
                    return $decoded !== null ? $decoded : $v;
                })
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Save multiple settings for a category atomically.
     */
    public static function setCategory(string $category, array $settings, ?int $userId = null): void
    {
        foreach ($settings as $key => $value) {
            static::setValue($category, $key, $value, $userId);
        }
    }
}
