<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payroll_settings')) return;

        // Check if this is the OLD schema (has closer_pct) or NEW schema (has setting_key)
        $hasOldSchema = Schema::hasColumn('payroll_settings', 'closer_pct');
        $hasNewSchema = Schema::hasColumn('payroll_settings', 'setting_key');

        if ($hasOldSchema && !$hasNewSchema) {
            // OLD table exists — rename it and create new one
            Schema::rename('payroll_settings', 'payroll_settings_legacy');

            Schema::create('payroll_settings', function (Blueprint $table) {
                $table->id();
                $table->string('setting_key', 100)->unique();
                $table->text('setting_value');
                $table->string('setting_type', 50)->default('string');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('payroll_settings', 'setting_key')) return;

        // Seed defaults
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

    public function down(): void {}
};
