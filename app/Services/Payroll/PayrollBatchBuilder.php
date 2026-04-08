<?php

namespace App\Services\Payroll;

use App\Models\Deal;
use App\Models\DealFinancial;
use App\Models\FinanceAudit;
use App\Models\PayrollBatchDeal;
use App\Models\PayrollBatchItem;
use App\Models\PayrollBatchV2;
use App\Models\PayrollSettingModel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds weekly payroll batches from eligible deals.
 */
class PayrollBatchBuilder
{
    /**
     * Build a weekly payroll batch.
     */
    public static function buildWeeklyBatch(Carbon $start, Carbon $end): PayrollBatchV2
    {
        return DB::transaction(function () use ($start, $end) {
            $batchName = 'Payroll Week ' . $start->format('Y-m-d') . ' to ' . $end->format('Y-m-d');

            $batch = PayrollBatchV2::create([
                'batch_name' => $batchName,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'batch_status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            self::attachEligibleDeals($batch);
            self::buildBatchItems($batch);
            self::summarizeBatch($batch);

            FinanceAudit::record('PayrollBatch', $batch->id, 'batch_created', null, $batch->toArray(), $batchName);

            return $batch;
        });
    }

    /**
     * Attach eligible deals to the batch.
     */
    public static function attachEligibleDeals(PayrollBatchV2 $batch): void
    {
        $deals = Deal::whereBetween('payment_date', [$batch->period_start, $batch->period_end . ' 23:59:59'])
            ->whereIn('payroll_status', ['calculated', 'approved'])
            ->where('commission_status', '!=', 'reversed')
            ->whereHas('dealFinancial', fn($q) => $q->where('is_reversed', false))
            ->whereDoesntHave('payrollBatchDeals', function ($q) {
                $q->whereHas('batch', fn($bq) => $bq->whereIn('batch_status', ['paid', 'locked']));
            })
            ->get();

        foreach ($deals as $deal) {
            $financial = DealFinancial::where('deal_id', $deal->id)->first();
            if (!$financial) continue;

            PayrollBatchDeal::create([
                'payroll_batch_id' => $batch->id,
                'deal_id' => $deal->id,
                'deal_financial_id' => $financial->id,
            ]);
        }
    }

    /**
     * Build batch items grouped by user + role.
     */
    public static function buildBatchItems(PayrollBatchV2 $batch): void
    {
        $holdEnabled = PayrollSettingModel::get('commission_hold_enabled', true);
        $holdPct = PayrollSettingModel::get('commission_hold_percent', 10.00);

        $batchDeals = $batch->batchDeals()->with(['deal', 'dealFinancial'])->get();

        // Accumulate per user+role
        $userRoles = []; // key: "{user_id}_{role}" => aggregated data

        foreach ($batchDeals as $bd) {
            $fin = $bd->dealFinancial;
            if (!$fin) continue;

            $deal = $bd->deal;
            $base = $fin->calculation_base;

            // Fronter
            if ($deal->fronter_user_id) {
                $key = $deal->fronter_user_id . '_fronter';
                if (!isset($userRoles[$key])) {
                    $userRoles[$key] = ['user_id' => $deal->fronter_user_id, 'role' => 'fronter', 'gross' => 0, 'deals' => 0, 'commission' => 0];
                }
                $userRoles[$key]['gross'] += $base;
                $userRoles[$key]['deals']++;
                $userRoles[$key]['commission'] += (float) $fin->fronter_commission;
            }

            // Closer
            $closerId = $deal->closer_user_id_payroll ?? $deal->closer;
            if ($closerId) {
                $key = $closerId . '_closer';
                if (!isset($userRoles[$key])) {
                    $userRoles[$key] = ['user_id' => $closerId, 'role' => 'closer', 'gross' => 0, 'deals' => 0, 'commission' => 0];
                }
                $userRoles[$key]['gross'] += $base;
                $userRoles[$key]['deals']++;
                $userRoles[$key]['commission'] += (float) $fin->closer_commission;
            }

            // Admin
            $adminId = $deal->admin_user_id_payroll ?? $deal->assigned_admin;
            if ($adminId) {
                $key = $adminId . '_admin';
                if (!isset($userRoles[$key])) {
                    $userRoles[$key] = ['user_id' => $adminId, 'role' => 'admin', 'gross' => 0, 'deals' => 0, 'commission' => 0];
                }
                $userRoles[$key]['gross'] += $base;
                $userRoles[$key]['deals']++;
                $userRoles[$key]['commission'] += (float) $fin->admin_commission;
            }
        }

        // Create batch items
        foreach ($userRoles as $data) {
            $hold = $holdEnabled ? round($data['commission'] * ($holdPct / 100), 2) : 0;
            $finalPayout = round($data['commission'] - $hold, 2);

            PayrollBatchItem::create([
                'payroll_batch_id' => $batch->id,
                'user_id' => $data['user_id'],
                'role_code' => $data['role'],
                'gross_volume' => round($data['gross'], 2),
                'deal_count' => $data['deals'],
                'base_commission' => round($data['commission'], 2),
                'hold_amount' => $hold,
                'final_payout' => $finalPayout,
                'payout_status' => 'pending',
            ]);
        }
    }

    /**
     * Summarize batch totals.
     */
    public static function summarizeBatch(PayrollBatchV2 $batch): void
    {
        $batchDeals = $batch->batchDeals()->with('dealFinancial')->get();

        $totalGross = 0;
        $totalComm = 0;
        $totalProcessing = 0;
        $totalReserve = 0;
        $totalMarketing = 0;
        $totalNet = 0;

        foreach ($batchDeals as $bd) {
            $fin = $bd->dealFinancial;
            if (!$fin) continue;

            $totalGross += $fin->calculation_base;
            $totalComm += $fin->total_commissions;
            $totalProcessing += (float) $fin->processing_fee;
            $totalReserve += (float) $fin->reserve_fee;
            $totalMarketing += (float) $fin->marketing_cost;
            $totalNet += (float) $fin->company_net;
        }

        $batch->update([
            'total_gross' => round($totalGross, 2),
            'total_commissions' => round($totalComm, 2),
            'total_processing' => round($totalProcessing, 2),
            'total_reserve' => round($totalReserve, 2),
            'total_marketing' => round($totalMarketing, 2),
            'total_company_net' => round($totalNet, 2),
        ]);
    }

    /**
     * Approve a batch.
     */
    public static function approveBatch(PayrollBatchV2 $batch): void
    {
        $batch->update([
            'batch_status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        FinanceAudit::record('PayrollBatch', $batch->id, 'batch_approved', null, $batch->toArray());
    }

    /**
     * Lock a batch.
     */
    public static function lockBatch(PayrollBatchV2 $batch): void
    {
        DB::transaction(function () use ($batch) {
            $batch->update([
                'batch_status' => 'locked',
                'locked_by' => auth()->id(),
                'locked_at' => now(),
            ]);

            // Lock all included deal financials
            foreach ($batch->batchDeals as $bd) {
                DealFinancial::where('id', $bd->deal_financial_id)->update([
                    'is_locked' => true,
                    'locked_at' => now(),
                ]);
                Deal::where('id', $bd->deal_id)->update([
                    'commission_status' => 'locked',
                    'payroll_locked_at' => now(),
                    'payroll_locked_by' => auth()->id(),
                ]);
            }

            FinanceAudit::record('PayrollBatch', $batch->id, 'batch_locked', null, $batch->toArray());
        });
    }

    /**
     * Mark batch as paid.
     */
    public static function markPaid(PayrollBatchV2 $batch): void
    {
        DB::transaction(function () use ($batch) {
            $batch->update([
                'batch_status' => 'paid',
                'paid_by' => auth()->id(),
                'paid_at' => now(),
            ]);

            $batch->items()->update(['payout_status' => 'paid']);

            foreach ($batch->batchDeals as $bd) {
                Deal::where('id', $bd->deal_id)->update([
                    'payroll_status' => 'paid',
                    'commission_status' => 'paid',
                ]);
            }

            FinanceAudit::record('PayrollBatch', $batch->id, 'batch_paid', null, $batch->toArray());
        });
    }
}
