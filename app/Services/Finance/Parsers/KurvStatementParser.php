<?php

namespace App\Services\Finance\Parsers;

/**
 * KURV Payment statement parser.
 * Inherits BaseStatementParser utilities.
 */
class KurvStatementParser extends BaseStatementParser
{
    public function version(): string { return '2.0.0'; }
    public function slug(): string { return 'kurv'; }

    protected function parseHeader(string $text): array
    {
        $header = $this->emptyResult()['header'];
        $header['processor_name'] = 'KURV';

        if (preg_match('/(?:Merchant|DBA|Business)\s*[:\-]?\s*(.+)/i', $text, $m)) {
            $header['merchant_name'] = trim($m[1]);
        }
        if (preg_match('/(?:Merchant\s*(?:#|No|Number)|MID|Account)\s*[:\-]?\s*([\d\-\*]+)/i', $text, $m)) {
            $header['merchant_number'] = trim($m[1]);
        }
        $header['statement_month'] = $this->parseStatementMonth($text);
        $header['routing_last4'] = $this->extractLast4($text, 'Routing');
        $header['deposit_account_last4'] = $this->extractLast4($text, 'Account') ?? $this->extractLast4($text, 'DDA');

        $this->log('info', 'header', 'Header parsed', $header);
        return $header;
    }

    protected function parsePlanSummary(string $text): array
    {
        $plans = [];
        $section = $this->extractSection($text, 'Plan\s+Summary|Card\s+(?:Brand|Type)', 'Deposit|Chargeback|Fee|End');
        if (empty($section)) return $plans;

        $lines = preg_split('/\r?\n/', $section);
        foreach ($lines as $line) {
            if (preg_match('/^\s*([A-Z]{2,10})\s+(\d+)\s+\$?([\d,\.]+)/i', trim($line), $m)) {
                $salesAmt = $this->parseMoney($m[3]);
                $cnt = (int) $m[2];
                $plans[] = [
                    'card_brand' => strtoupper(trim($m[1])),
                    'plan_code' => null, 'sales_count' => $cnt, 'sales_amount' => $salesAmt,
                    'credit_count' => 0, 'credit_amount' => 0, 'net_sales' => $salesAmt,
                    'average_ticket' => $cnt > 0 ? round($salesAmt / $cnt, 2) : 0,
                    'discount_rate' => 0, 'discount_due' => 0,
                ];
            }
        }
        return $plans;
    }

    protected function parseDeposits(string $text): array
    {
        return $this->genericParseDeposits($text);
    }

    protected function parseChargebacks(string $text): array
    {
        return $this->genericParseChargebacks($text);
    }

    protected function parseReserves(string $text): array
    {
        return $this->genericParseReserves($text);
    }

    protected function parseFees(string $text): array
    {
        return $this->genericParseFees($text);
    }

    protected function parseSummaryTotals(string $text, array $parsed): array
    {
        return $this->genericParseSummaryTotals($text, $parsed);
    }

    // ── Generic fallback methods also used by other thin parsers ──

    protected function genericParseDeposits(string $text): array
    {
        $deposits = [];

        // Try multiple section header patterns
        $sectionPatterns = [
            'Deposit\s+(?:Detail|Activity|Summary)',
            'Daily\s+Deposit',
            'Deposit',
        ];

        $section = '';
        foreach ($sectionPatterns as $sp) {
            $section = $this->extractSection($text, $sp, 'Chargeback|Dispute|Adjustment|Reserve|Fee\s+(?:Detail|Summary)|End\s+of|Page\s+\d+\s+of');
            if (!empty($section)) break;
        }

        if (empty($section)) return $deposits;

        $lines = preg_split('/\r?\n/', $section);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || preg_match('/^\s*(Day|Date|Total|Page|Subtotal|Continued)/i', $line)) continue;

            // Full row: day  reference  tran  plan  count  sales  credits  discount  net
            if (preg_match('/^\s*(\d{1,2})\s+(\d{6,20})\s+([A-Z]{1,4})\s+([A-Z]{2,6})?\s*(\d+)\s+([\d,\.]+)\s+([\d,\.]+)\s+([\d,\.]+)\s+([\-\d,\.]+)/', $line, $m)) {
                $deposits[] = [
                    'deposit_day' => (int) $m[1], 'reference_number' => $m[2],
                    'tran_code' => $m[3], 'plan_code' => !empty($m[4]) ? $m[4] : null,
                    'sales_count' => (int) $m[5], 'sales_amount' => $this->parseMoney($m[6]),
                    'credits_amount' => $this->parseMoney($m[7]), 'discount_paid' => $this->parseMoney($m[8]),
                    'net_deposit' => $this->parseMoney($m[9]), 'raw_row_text' => $line,
                ];
            }
            // Medium: day  reference  count  sales  credits  discount  net
            elseif (preg_match('/^\s*(\d{1,2})\s+(\d{6,20})\s+(\d+)\s+\$?([\d,\.]+)\s+\$?([\d,\.]+)\s+\$?([\d,\.]+)\s+\$?([\-\d,\.]+)/', $line, $m)) {
                $deposits[] = [
                    'deposit_day' => (int) $m[1], 'reference_number' => $m[2],
                    'tran_code' => null, 'plan_code' => null,
                    'sales_count' => (int) $m[3], 'sales_amount' => $this->parseMoney($m[4]),
                    'credits_amount' => $this->parseMoney($m[5]), 'discount_paid' => $this->parseMoney($m[6]),
                    'net_deposit' => $this->parseMoney($m[7]), 'raw_row_text' => $line,
                ];
            }
            // Minimal: day  reference  net_deposit
            elseif (preg_match('/^\s*(\d{1,2})\s+(\d{6,20})\s+.*?([\-\d,\.]+)\s*$/', $line, $m)) {
                $deposits[] = [
                    'deposit_day' => (int) $m[1], 'reference_number' => $m[2],
                    'tran_code' => null, 'plan_code' => null, 'sales_count' => 0,
                    'sales_amount' => 0, 'credits_amount' => 0, 'discount_paid' => 0,
                    'net_deposit' => $this->parseMoney($m[3]), 'raw_row_text' => $line,
                ];
            }
        }
        return $deposits;
    }

    protected function genericParseChargebacks(string $text): array
    {
        $chargebacks = [];

        // Try multiple section header patterns
        $sectionPatterns = [
            'Chargeback\s+(?:Detail|Activity|Summary)',
            'Chargeback',
            'Dispute\s+(?:Detail|Activity|Summary)',
            'Adjustment\s+(?:Detail|Activity)',
            'CB\s+(?:Detail|Activity)',
        ];

        $section = '';
        foreach ($sectionPatterns as $sp) {
            $section = $this->extractSection($text, $sp, 'Reserve|Fee\s+(?:Detail|Summary)|Summary\s+Totals|End\s+of|Page\s+\d+\s+of');
            if (!empty($section)) break;
        }

        if (empty($section)) {
            // Fallback: scan full text for chargeback tran code rows
            $chargebacks = $this->fallbackChargebackScan($text);
            if (!empty($chargebacks)) {
                $this->log('info', 'chargebacks', 'Chargebacks found via fallback scan: ' . count($chargebacks));
            }
            return $chargebacks;
        }

        $lines = preg_split('/\r?\n/', $section);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || preg_match('/^\s*(Day|Date|Total|Page|Subtotal|Section|Number|Ref)/i', $line)) continue;

            // Pattern 1: day  ref  tran_code  card_brand  reason  amount
            if (preg_match('/^\s*(\d{1,2})\s+(\d{6,20})\s+([A-Z]{1,4})\s+([A-Z]{2,6})?\s*(?:(\d{2,6})\s+)?\$?([\-\d,\.]+)/', $line, $m) && isset($m[6])) {
                $eventType = $this->mapTranCodeToEventType($m[3]);
                $chargebacks[] = [
                    'chargeback_day' => (int) $m[1], 'reference_number' => $m[2], 'tran_code' => $m[3],
                    'amount' => abs($this->parseMoney($m[6])), 'event_type' => $eventType,
                    'recovered_flag' => in_array($eventType, ['reversal', 'representment_credit']),
                    'card_brand' => !empty($m[4]) ? $m[4] : null, 'reason_code' => !empty($m[5]) ? $m[5] : null,
                    'case_number' => null, 'raw_row_text' => $line,
                ];
            }
            // Pattern 2: day  ref  tran_code  amount
            elseif (preg_match('/^\s*(\d{1,2})\s+(\d{6,20})\s+([A-Z]{1,4})\s+\$?([\-\d,\.]+)/', $line, $m)) {
                $eventType = $this->mapTranCodeToEventType($m[3]);
                $chargebacks[] = [
                    'chargeback_day' => (int) $m[1], 'reference_number' => $m[2], 'tran_code' => $m[3],
                    'amount' => abs($this->parseMoney($m[4])), 'event_type' => $eventType,
                    'recovered_flag' => in_array($eventType, ['reversal', 'representment_credit']),
                    'card_brand' => null, 'reason_code' => null, 'case_number' => null,
                    'raw_row_text' => $line,
                ];
            }
            // Pattern 3: day  ref  amount (no tran code)
            elseif (preg_match('/^\s*(\d{1,2})\s+(\d{6,20})\s+\$?([\-\d,\.]+)/', $line, $m)) {
                $chargebacks[] = [
                    'chargeback_day' => (int) $m[1], 'reference_number' => $m[2], 'tran_code' => null,
                    'amount' => abs($this->parseMoney($m[3])), 'event_type' => 'chargeback',
                    'recovered_flag' => false,
                    'card_brand' => null, 'reason_code' => null, 'case_number' => null,
                    'raw_row_text' => $line,
                ];
            }
            // Pattern 4: MM/DD  ref  tran_code  amount
            elseif (preg_match('/^\s*(\d{1,2})\/\d{1,2}\s+(\d{6,20})\s+([A-Z]{1,4})\s+\$?([\-\d,\.]+)/', $line, $m)) {
                $eventType = $this->mapTranCodeToEventType($m[3]);
                $chargebacks[] = [
                    'chargeback_day' => (int) $m[1], 'reference_number' => $m[2], 'tran_code' => $m[3],
                    'amount' => abs($this->parseMoney($m[4])), 'event_type' => $eventType,
                    'recovered_flag' => in_array($eventType, ['reversal', 'representment_credit']),
                    'card_brand' => null, 'reason_code' => null, 'case_number' => null,
                    'raw_row_text' => $line,
                ];
            }
            // Pattern 5: MM/DD  ref  amount
            elseif (preg_match('/^\s*(\d{1,2})\/\d{1,2}\s+(\d{6,20})\s+\$?([\-\d,\.]+)/', $line, $m)) {
                $chargebacks[] = [
                    'chargeback_day' => (int) $m[1], 'reference_number' => $m[2], 'tran_code' => null,
                    'amount' => abs($this->parseMoney($m[3])), 'event_type' => 'chargeback',
                    'recovered_flag' => false,
                    'card_brand' => null, 'reason_code' => null, 'case_number' => null,
                    'raw_row_text' => $line,
                ];
            }
        }

        $this->log('info', 'chargebacks', 'Parsed ' . count($chargebacks) . ' chargeback rows');
        return $chargebacks;
    }

    protected function genericParseReserves(string $text): array
    {
        $reserves = [];
        if (preg_match('/Reserve\s+(?:Ending|Balance)\s*[:\-]?\s*\$?([\d,\.]+)/i', $text, $m)) {
            $reserves[] = ['reserve_day' => null, 'reserve_amount' => 0, 'release_amount' => 0,
                'running_balance' => $this->parseMoney($m[1]), 'raw_row_text' => $m[0]];
        }
        return $reserves;
    }

    protected function genericParseFees(string $text): array
    {
        $fees = [];

        $sectionPatterns = [
            'Fee\s+(?:Detail|Summary|Breakdown)',
            'Itemized\s+Fees',
            'Other\s+Fees',
            'Fee',
        ];

        $section = '';
        foreach ($sectionPatterns as $sp) {
            $section = $this->extractSection($text, $sp, 'Summary\s+Totals|Totals|End\s+of|Page\s+\d+\s+of');
            if (!empty($section)) break;
        }

        if (empty($section)) return $fees;

        $lines = preg_split('/\r?\n/', $section);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || preg_match('/^\s*(Description|Fee|Total|Page|Subtotal)/i', $line)) continue;

            // Pattern 1: description  qty  basis  fee_total
            if (preg_match('/^\s*(.{10,50}?)\s+(\d+)\s+\$?([\d,\.]+)\s+\$?([\-\d,\.]+)\s*$/', $line, $m)) {
                $desc = trim($m[1]);
                $fees[] = [
                    'fee_description' => $desc,
                    'fee_category' => \App\Models\StatementFee::categorizeDescription($desc),
                    'quantity' => (int) $m[2], 'basis_amount' => $this->parseMoney($m[3]),
                    'rate' => null, 'fee_total' => $this->parseMoney($m[4]), 'raw_row_text' => $line,
                ];
            }
            // Pattern 2: description  fee_total
            elseif (preg_match('/^\s*(.{10,50}?)\s+\$?([\-\d,\.]+)\s*$/', $line, $m)) {
                $desc = trim($m[1]);
                if (preg_match('/^(total|subtotal)/i', $desc)) continue;
                $fees[] = [
                    'fee_description' => $desc,
                    'fee_category' => \App\Models\StatementFee::categorizeDescription($desc),
                    'quantity' => 0, 'basis_amount' => 0,
                    'rate' => null, 'fee_total' => $this->parseMoney($m[2]), 'raw_row_text' => $line,
                ];
            }
        }
        return $fees;
    }

    protected function genericParseSummaryTotals(string $text, array $parsed): array
    {
        $summary = $this->emptyResult()['summary'];
        $fields = [
            'gross_sales' => '/Gross\s+(?:Sales|Volume)\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'credits' => '/Credits?\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'net_sales' => '/Net\s+Sales\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'discount_paid' => '/Discount\s+Paid\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'fees_paid' => '/Fees?\s+Paid\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'amount_deducted' => '/(?:Amount\s+Deducted|Total\s+Deductions?)\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'reserve_ending_balance' => '/Reserve\s+(?:Ending|End)?\s*Balance\s*[:\-]?\s*\$?([\d,\.]+)/i',
        ];
        foreach ($fields as $key => $pattern) {
            if (preg_match($pattern, $text, $m)) $summary[$key] = $this->parseMoney($m[1]);
        }
        if ($summary['total_deposits'] == 0 && !empty($parsed['deposits'])) {
            $summary['total_deposits'] = array_sum(array_column($parsed['deposits'], 'net_deposit'));
        }
        if (!empty($parsed['chargebacks'])) {
            $cbs = array_filter($parsed['chargebacks'], fn($c) => $c['event_type'] === 'chargeback');
            $summary['total_chargebacks'] = array_sum(array_column($cbs, 'amount'));
            $revs = array_filter($parsed['chargebacks'], fn($c) => in_array($c['event_type'], ['reversal', 'representment_credit']));
            $summary['total_reversals'] = array_sum(array_column($revs, 'amount'));
        }
        if ($summary['net_sales'] == 0 && $summary['gross_sales'] > 0) {
            $summary['net_sales'] = $summary['gross_sales'] - $summary['credits'];
        }
        return $summary;
    }
}
