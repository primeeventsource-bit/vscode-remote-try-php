<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class FinanceSetting extends Model
{
    protected $fillable = ['key', 'value'];
    protected $casts = ['value' => 'array'];

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            if (!Schema::hasTable('finance_settings')) return $default;
            $row = self::where('key', $key)->first();
            return $row ? $row->value : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function set(string $key, mixed $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function getMany(array $keys): array
    {
        $result = [];
        foreach ($keys as $key => $default) {
            $result[$key] = self::get($key, $default);
        }
        return $result;
    }
}
