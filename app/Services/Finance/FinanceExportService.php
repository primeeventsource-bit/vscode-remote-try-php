<?php

namespace App\Services\Finance;

use App\Models\MerchantStatement;
use App\Models\StatementDeposit;
use App\Models\StatementChargeback;
use App\Models\StatementFee;
use Illuminate\Support\Facades\Storage;

/**
 * Generates CSV/XLSX exports for finance data.
 */
class FinanceExportService
{
    /**
     * Export deposits to CSV.
     */
    public static function exportDeposits(array $filters = []): string
    {
        $query = StatementDeposit::query()
            ->join('merchant_statements', 'statement_deposits.statement_id', '=', 'merchant_statements.id')
            ->join('merchant_accounts', 'merchant_statements.merchant_account_id', '=', 'merchant_accounts.id')
            ->where('merchant_statements.ai_parse_status', 'completed');

        if (!empty($filters['merchant_account_id'])) {
            $query->where('merchant_statements.merchant_account_id', $filters['merchant_account_id']);
        }
        if (!empty($filters['month'])) {
            $query->where('merchant_statements.statement_month', $filters['month']);
        }

        $rows = $query->select([
            'statement_deposits.deposit_date',
            'statement_deposits.reference_number',
            'statement_deposits.sales_count',
            'statement_deposits.sales_amount',
            'statement_deposits.credits_amount',
            'statement_deposits.discount_paid',
            'statement_deposits.net_deposit',
            'merchant_statements.statement_month',
            'merchant_accounts.name as merchant_name',
        ])->orderBy('statement_deposits.deposit_date')->get();

        return self::toCsv($rows->toArray(), [
            'Deposit Date', 'Reference', 'Sales Count', 'Sales Amount',
            'Credits', 'Discount Paid', 'Net Deposit', 'Statement Month', 'Merchant',
        ]);
    }

    /**
     * Export chargebacks to CSV.
     */
    public static function exportChargebacks(array $filters = [], string $source = 'statement'): string
    {
        if ($source === 'live') {
            $query = \App\Models\LiveChargeback::query()
                ->join('merchant_accounts', 'live_chargebacks.merchant_account_id', '=', 'merchant_accounts.id');

            if (!empty($filters['merchant_account_id'])) {
                $query->where('live_chargebacks.merchant_account_id', $filters['merchant_account_id']);
            }

            $rows = $query->select([
                'live_chargebacks.dispute_date',
                'live_chargebacks.reference_number',
                'live_chargebacks.amount',
                'live_chargebacks.event_type',
                'live_chargebacks.status',
                'live_chargebacks.card_brand',
                'live_chargebacks.reason_code',
                'live_chargebacks.notes',
                'merchant_accounts.name as merchant_name',
            ])->orderByDesc('live_chargebacks.created_at')->get();

            return self::toCsv($rows->toArray(), [
                'Dispute Date', 'Reference', 'Amount', 'Type', 'Status',
                'Card Brand', 'Reason Code', 'Notes', 'Merchant',
            ]);
        }

        $query = StatementChargeback::query()
            ->join('merchant_statements', 'statement_chargebacks.statement_id', '=', 'merchant_statements.id')
            ->join('merchant_accounts', 'merchant_statements.merchant_account_id', '=', 'merchant_accounts.id')
            ->where('merchant_statements.ai_parse_status', 'completed');

        if (!empty($filters['merchant_account_id'])) {
            $query->where('merchant_statements.merchant_account_id', $filters['merchant_account_id']);
        }
        if (!empty($filters['month'])) {
            $query->where('merchant_statements.statement_month', $filters['month']);
        }

        $rows = $query->select([
            'statement_chargebacks.chargeback_date',
            'statement_chargebacks.reference_number',
            'statement_chargebacks.amount',
            'statement_chargebacks.event_type',
            'statement_chargebacks.tran_code',
            'statement_chargebacks.recovered_flag',
            'merchant_statements.statement_month',
            'merchant_accounts.name as merchant_name',
        ])->orderBy('statement_chargebacks.chargeback_date')->get();

        return self::toCsv($rows->toArray(), [
            'Date', 'Reference', 'Amount', 'Event Type', 'Tran Code',
            'Recovered', 'Statement Month', 'Merchant',
        ]);
    }

    /**
     * Export fees to CSV.
     */
    public static function exportFees(array $filters = []): string
    {
        $query = StatementFee::query()
            ->join('merchant_statements', 'statement_fees.statement_id', '=', 'merchant_statements.id')
            ->join('merchant_accounts', 'merchant_statements.merchant_account_id', '=', 'merchant_accounts.id')
            ->where('merchant_statements.ai_parse_status', 'completed');

        if (!empty($filters['merchant_account_id'])) {
            $query->where('merchant_statements.merchant_account_id', $filters['merchant_account_id']);
        }
        if (!empty($filters['month'])) {
            $query->where('merchant_statements.statement_month', $filters['month']);
        }

        $rows = $query->select([
            'statement_fees.fee_description',
            'statement_fees.fee_category',
            'statement_fees.quantity',
            'statement_fees.basis_amount',
            'statement_fees.fee_total',
            'merchant_statements.statement_month',
            'merchant_accounts.name as merchant_name',
        ])->orderBy('statement_fees.fee_category')->get();

        return self::toCsv($rows->toArray(), [
            'Description', 'Category', 'Quantity', 'Basis Amount',
            'Fee Total', 'Statement Month', 'Merchant',
        ]);
    }

    /**
     * Export profit summary to CSV.
     */
    public static function exportProfit(array $filters = []): string
    {
        $query = \App\Models\MerchantProfitSnapshot::query()
            ->join('merchant_accounts', 'merchant_profit_snapshots.merchant_account_id', '=', 'merchant_accounts.id');

        if (!empty($filters['merchant_account_id'])) {
            $query->where('merchant_profit_snapshots.merchant_account_id', $filters['merchant_account_id']);
        }
        if (!empty($filters['month'])) {
            $query->where('merchant_profit_snapshots.snapshot_month', $filters['month']);
        }

        $rows = $query->select([
            'merchant_accounts.name as merchant_name',
            'merchant_profit_snapshots.snapshot_month',
            'merchant_profit_snapshots.gross_sales',
            'merchant_profit_snapshots.net_deposits',
            'merchant_profit_snapshots.discount_fees',
            'merchant_profit_snapshots.other_processor_fees',
            'merchant_profit_snapshots.net_chargeback_loss',
            'merchant_profit_snapshots.payroll_cost',
            'merchant_profit_snapshots.operating_expenses',
            'merchant_profit_snapshots.true_net_profit',
            'merchant_profit_snapshots.profit_margin_pct',
            'merchant_profit_snapshots.mid_health_score',
        ])->orderBy('merchant_profit_snapshots.snapshot_month')->get();

        return self::toCsv($rows->toArray(), [
            'Merchant', 'Month', 'Gross Sales', 'Net Deposits', 'Discount Fees',
            'Other Fees', 'Net CB Loss', 'Payroll', 'OpEx', 'True Net Profit',
            'Margin %', 'Health Score',
        ]);
    }

    /**
     * Convert array data to CSV string.
     */
    private static function toCsv(array $rows, array $headers): string
    {
        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, array_values((array) $row));
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        return $csv;
    }
}
