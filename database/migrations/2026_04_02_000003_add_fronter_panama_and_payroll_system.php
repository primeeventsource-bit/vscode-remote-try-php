<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add VD deal flag and commission fields to deals
        Schema::table('deals', function (Blueprint $table) {
            if (!Schema::hasColumn('deals', 'is_vd_deal')) {
                $table->boolean('is_vd_deal')->default(false)->after('was_vd');
            }
            if (!Schema::hasColumn('deals', 'fronter_role')) {
                $table->string('fronter_role', 30)->nullable()->after('fronter');
            }
            if (!Schema::hasColumn('deals', 'closer_comm_pct')) {
                $table->decimal('closer_comm_pct', 5, 2)->nullable()->after('fee');
            }
            if (!Schema::hasColumn('deals', 'closer_comm_amount')) {
                $table->decimal('closer_comm_amount', 10, 2)->nullable()->after('closer_comm_pct');
            }
            if (!Schema::hasColumn('deals', 'fronter_comm_amount')) {
                $table->decimal('fronter_comm_amount', 10, 2)->nullable()->after('closer_comm_amount');
            }
            if (!Schema::hasColumn('deals', 'snr_deduction')) {
                $table->decimal('snr_deduction', 10, 2)->nullable()->after('fronter_comm_amount');
            }
            if (!Schema::hasColumn('deals', 'vd_deduction')) {
                $table->decimal('vd_deduction', 10, 2)->nullable()->after('snr_deduction');
            }
            if (!Schema::hasColumn('deals', 'closer_net_pay')) {
                $table->decimal('closer_net_pay', 10, 2)->nullable()->after('vd_deduction');
            }
            if (!Schema::hasColumn('deals', 'payroll_week')) {
                $table->string('payroll_week', 20)->nullable()->after('closer_net_pay');
            }
            if (!Schema::hasColumn('deals', 'payroll_finalized')) {
                $table->boolean('payroll_finalized')->default(false)->after('payroll_week');
            }
        });

        // Payroll reports table
        if (!Schema::hasTable('payroll_reports')) {
            Schema::create('payroll_reports', function (Blueprint $table) {
                $table->id();
                $table->string('week_label', 50);
                $table->date('week_start');
                $table->date('week_end');
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('user_role', 30);
                $table->decimal('total_deals_amount', 12, 2)->default(0);
                $table->decimal('total_commission', 10, 2)->default(0);
                $table->decimal('total_snr', 10, 2)->default(0);
                $table->decimal('total_vd', 10, 2)->default(0);
                $table->decimal('net_pay', 10, 2)->default(0);
                $table->integer('deal_count')->default(0);
                $table->json('deal_details')->nullable();
                $table->string('status', 20)->default('draft');
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('finalized_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'week_start']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_reports');
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn([
                'is_vd_deal', 'fronter_role', 'closer_comm_pct', 'closer_comm_amount',
                'fronter_comm_amount', 'snr_deduction', 'vd_deduction', 'closer_net_pay',
                'payroll_week', 'payroll_finalized',
            ]);
        });
    }
};
