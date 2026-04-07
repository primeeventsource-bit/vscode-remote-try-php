<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_entries')) {
            return;
        }

        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id');
            $table->string('user_id', 50);
            $table->string('user_name', 100);
            $table->string('user_role', 50);
            $table->string('pay_type', 20);
            $table->decimal('total_sold', 12, 2)->default(0.00);
            $table->decimal('total_payout', 12, 2)->default(0.00);
            $table->decimal('vd_taken', 12, 2)->default(0.00);
            $table->decimal('commission_pct', 5, 2)->default(0.00);
            $table->decimal('commission_amount', 12, 2)->default(0.00);
            $table->decimal('fronter_cut', 12, 2)->default(0.00);
            $table->decimal('snr_amount', 12, 2)->default(0.00);
            $table->decimal('hourly_hours', 6, 2)->default(0.00);
            $table->decimal('hourly_rate', 8, 2)->default(0.00);
            $table->decimal('hourly_pay', 12, 2)->default(0.00);
            $table->decimal('gross_pay', 12, 2)->default(0.00);
            $table->decimal('cb_total', 12, 2)->default(0.00);
            $table->decimal('net_pay', 12, 2)->default(0.00);
            $table->decimal('final_pay', 12, 2)->default(0.00);
            $table->integer('deal_count')->default(0);
            $table->integer('cb_count')->default(0);
            $table->integer('vd_count')->default(0);
            $table->text('deals_json')->nullable();
            $table->string('status', 20)->default('draft');
            $table->dateTime('sent_at')->nullable();
            $table->string('sent_by', 100)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('run_id')->references('id')->on('payroll_runs');
            $table->unique(['run_id', 'user_id'], 'uq_entries_user_run');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
    }
};
