<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_user_rates')) {
            return;
        }

        Schema::create('payroll_user_rates', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 50);
            $table->decimal('comm_pct', 5, 2)->nullable();
            $table->decimal('snr_pct', 5, 2)->nullable();
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique('user_id', 'uq_user_rates_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_user_rates');
    }
};
