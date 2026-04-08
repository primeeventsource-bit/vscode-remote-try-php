<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            if (!Schema::hasColumn('deals', 'gross_amount')) {
                $table->decimal('gross_amount', 12, 2)->default(0)->after('fee');
            }
            if (!Schema::hasColumn('deals', 'collected_amount')) {
                $table->decimal('collected_amount', 12, 2)->default(0)->after('gross_amount');
            }
            if (!Schema::hasColumn('deals', 'refunded_amount')) {
                $table->decimal('refunded_amount', 12, 2)->default(0)->after('collected_amount');
            }
            if (!Schema::hasColumn('deals', 'chargeback_amount')) {
                $table->decimal('chargeback_amount', 12, 2)->default(0)->after('refunded_amount');
            }
            if (!Schema::hasColumn('deals', 'fronter_user_id')) {
                $table->unsignedBigInteger('fronter_user_id')->nullable()->after('chargeback_amount');
            }
            if (!Schema::hasColumn('deals', 'closer_user_id_payroll')) {
                $table->unsignedBigInteger('closer_user_id_payroll')->nullable()->after('fronter_user_id');
            }
            if (!Schema::hasColumn('deals', 'admin_user_id_payroll')) {
                $table->unsignedBigInteger('admin_user_id_payroll')->nullable()->after('closer_user_id_payroll');
            }
            if (!Schema::hasColumn('deals', 'payroll_status')) {
                $table->string('payroll_status', 50)->default('pending')->after('admin_user_id_payroll');
            }
            if (!Schema::hasColumn('deals', 'commission_status')) {
                $table->string('commission_status', 50)->default('pending')->after('payroll_status');
            }
            if (!Schema::hasColumn('deals', 'payroll_locked_at')) {
                $table->timestamp('payroll_locked_at')->nullable()->after('commission_status');
            }
            if (!Schema::hasColumn('deals', 'payroll_locked_by')) {
                $table->unsignedBigInteger('payroll_locked_by')->nullable()->after('payroll_locked_at');
            }
            if (!Schema::hasColumn('deals', 'payroll_notes')) {
                $table->text('payroll_notes')->nullable()->after('payroll_locked_by');
            }
            if (!Schema::hasColumn('deals', 'is_refunded')) {
                $table->boolean('is_refunded')->default(false)->after('payroll_notes');
            }
            if (!Schema::hasColumn('deals', 'is_chargeback')) {
                $table->boolean('is_chargeback')->default(false)->after('is_refunded');
            }
            if (!Schema::hasColumn('deals', 'processor_name_payroll')) {
                $table->string('processor_name_payroll', 100)->nullable()->after('is_chargeback');
            }
            if (!Schema::hasColumn('deals', 'mid_name')) {
                $table->string('mid_name', 100)->nullable()->after('processor_name_payroll');
            }
            if (!Schema::hasColumn('deals', 'payment_date')) {
                $table->timestamp('payment_date')->nullable()->after('mid_name');
            }
            if (!Schema::hasColumn('deals', 'finance_snapshot_id')) {
                $table->unsignedBigInteger('finance_snapshot_id')->nullable()->after('payment_date');
            }

            // Indexes
            $table->index('payroll_status');
            $table->index('commission_status');
            $table->index('payment_date');
        });

        // Backfill: set gross_amount from fee, and user IDs from existing columns
        \Illuminate\Support\Facades\DB::statement("
            UPDATE deals SET
                gross_amount = COALESCE(fee, 0),
                collected_amount = CASE WHEN charged = 'yes' THEN COALESCE(fee, 0) ELSE 0 END,
                fronter_user_id = fronter,
                closer_user_id_payroll = closer,
                admin_user_id_payroll = assigned_admin,
                payment_date = COALESCE(charged_at, charged_date, timestamp)
            WHERE gross_amount = 0 OR gross_amount IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $cols = [
                'gross_amount', 'collected_amount', 'refunded_amount', 'chargeback_amount',
                'fronter_user_id', 'closer_user_id_payroll', 'admin_user_id_payroll',
                'payroll_status', 'commission_status', 'payroll_locked_at', 'payroll_locked_by',
                'payroll_notes', 'is_refunded', 'is_chargeback', 'processor_name_payroll',
                'mid_name', 'payment_date', 'finance_snapshot_id',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('deals', $col)) $table->dropColumn($col);
            }
        });
    }
};
