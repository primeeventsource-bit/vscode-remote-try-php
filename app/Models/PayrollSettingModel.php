<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PayrollSettingModel extends Model
{
    protected $table = 'payroll_settings';

    protected $fillable = ['setting_key', 'setting_value', 'setting_type', 'description'];

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            if (!Schema::hasTable('payroll_settings')) return $default;
            $row = self::where('setting_key', $key)->first();
            if (!$row) return $default;

            return match ($row->setting_type) {
                'decimal', 'float' => (float) $row->setting_value,
                'integer', 'int' => (int) $row->setting_value,
                'boolean', 'bool' => filter_var($row->setting_value, FILTER_VALIDATE_BOOLEAN),
                default => $row->setting_value,
            };
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function set(string $key, mixed $value, ?string $type = null): void
    {
        $row = self::where('setting_key', $key)->first();
        if ($row) {
            $row->update(['setting_value' => (string) $value, 'setting_type' => $type ?? $row->setting_type]);
        } else {
            self::create(['setting_key' => $key, 'setting_value' => (string) $value, 'setting_type' => $type ?? 'string']);
        }
    }

    public static function getDefaults(): array
    {
        return [
            'fronter_percent' => self::get('fronter_default_percent', 6.00),
            'closer_percent' => self::get('closer_default_percent', 12.00),
            'admin_percent' => self::get('admin_default_percent', 2.00),
            'processing_percent' => self::get('processing_default_percent', 3.00),
            'reserve_percent' => self::get('reserve_default_percent', 3.00),
            'marketing_percent' => self::get('marketing_default_percent', 15.00),
        ];
    }
}
