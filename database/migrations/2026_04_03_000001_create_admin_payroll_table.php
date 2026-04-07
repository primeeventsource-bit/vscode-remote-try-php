<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_payroll')) {
            Schema::create('admin_payroll', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admin_user_id')->constrained('users');
                $table->foreignId('entered_by_user_id')->constrained('users');
                $table->date('pay_period_start')->nullable();
                $table->date('pay_period_end')->nullable();
                $table->decimal('hours_worked', 6, 2)->default(0);
                $table->decimal('hourly_rate', 10, 2)->default(0);
                $table->decimal('commission_bonus', 10, 2)->default(0);
                $table->decimal('deductions', 10, 2)->default(0);
                $table->decimal('total_check_pay', 10, 2)->default(0);
                $table->text('notes')->nullable();
                $table->boolean('finalized')->default(false);
                $table->timestamps();
                $table->unique(['admin_user_id', 'pay_period_start']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_payroll');
    }
};
