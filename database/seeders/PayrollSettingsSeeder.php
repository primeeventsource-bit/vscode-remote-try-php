<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PayrollSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['setting_key' => 'fronter_default_percent', 'setting_value' => '6.00', 'setting_type' => 'decimal', 'description' => 'Default fronter commission percentage'],
            ['setting_key' => 'closer_default_percent', 'setting_value' => '12.00', 'setting_type' => 'decimal', 'description' => 'Default closer commission percentage'],
            ['setting_key' => 'admin_default_percent', 'setting_value' => '2.00', 'setting_type' => 'decimal', 'description' => 'Default admin/syfe commission percentage'],
            ['setting_key' => 'processing_default_percent', 'setting_value' => '3.00', 'setting_type' => 'decimal', 'description' => 'Default processing fee percentage'],
            ['setting_key' => 'reserve_default_percent', 'setting_value' => '3.00', 'setting_type' => 'decimal', 'description' => 'Default chargeback reserve percentage'],
            ['setting_key' => 'marketing_default_percent', 'setting_value' => '15.00', 'setting_type' => 'decimal', 'description' => 'Default marketing allocation percentage'],
            ['setting_key' => 'commission_hold_enabled', 'setting_value' => 'true', 'setting_type' => 'boolean', 'description' => 'Enable commission hold period'],
            ['setting_key' => 'commission_hold_percent', 'setting_value' => '10.00', 'setting_type' => 'decimal', 'description' => 'Percentage of commission to hold'],
            ['setting_key' => 'commission_hold_days', 'setting_value' => '14', 'setting_type' => 'integer', 'description' => 'Days to hold commission before release'],
            ['setting_key' => 'allow_admin_adjustments', 'setting_value' => 'true', 'setting_type' => 'boolean', 'description' => 'Allow admins to add manual adjustments'],
            ['setting_key' => 'auto_calculate_on_verified_charged', 'setting_value' => 'true', 'setting_type' => 'boolean', 'description' => 'Auto-calculate payroll when deal is verified and charged'],
        ];

        foreach ($settings as $s) {
            DB::table('payroll_settings')->updateOrInsert(
                ['setting_key' => $s['setting_key']],
                array_merge($s, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
