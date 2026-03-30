<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_settings')) {
            return;
        }

        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('closer_pct', 5, 2)->default(50.00);
            $table->decimal('fronter_pct', 5, 2)->default(10.00);
            $table->decimal('snr_pct', 5, 2)->default(2.00);
            $table->decimal('vd_pct', 5, 2)->default(3.00);
            $table->decimal('admin_snr_pct', 5, 2)->default(2.00);
            $table->decimal('hourly_rate', 8, 2)->default(19.50);
            $table->dateTime('updated_at')->nullable();
            $table->string('updated_by', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_settings');
    }
};
