<?php

namespace App\Services\Finance\Parsers;

/**
 * National Processing / NPC statement parser.
 */
class NationalProcessingStatementParser extends BaseStatementParser
{
    public function version(): string { return '2.0.0'; }
    public function slug(): string { return 'national_processing'; }

    protected function parseHeader(string $text): array
    {
        $header = $this->emptyResult()['header'];
        $header['processor_name'] = 'National Processing';

        if (preg_match('/(?:Merchant|DBA)\s*[:\-]?\s*(.+)/i', $text, $m)) {
            $header['merchant_name'] = trim($m[1]);
        }
        if (preg_match('/(?:Merchant\s*(?:#|No|Number)|MID)\s*[:\-]?\s*([\d\-\*]+)/i', $text, $m)) {
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
        $section = $this->extractSection($text, 'Plan\s+Summary|Card\s+Brand\s+Summary', 'Deposit|Chargeback|Fee|End');
        if (empty($section)) return $plans;

        $lines = preg_split('/\r?\n/', $section);
        foreach ($lines as $line) {
            if (preg_match('/^\s*([A-Z]{2,10})\s+(\d+)\s+\$?([\d,\.]+)\s+\$?([\d,\.]+)\s+\$?([\d,\.]+)/i', trim($line), $m)) {
                $salesAmt = $this->parseMoney($m[3]);
                $salesCnt = (int) $m[2];
                $plans[] = [
                    'card_brand' => strtoupper(trim($m[1])),
                    'plan_code' => null,
                    'sales_count' => $salesCnt,
                    'sales_amount' => $salesAmt,
                    'credit_count' => 0,
                    'credit_amount' => $this->parseMoney($m[4]),
                    'net_sales' => $this->parseMoney($m[5]),
                    'average_ticket' => $salesCnt > 0 ? round($salesAmt / $salesCnt, 2) : 0,
                    'discount_rate' => 0,
                    'discount_due' => 0,
                ];
            }
        }
        return $plans;
    }

    protected function parseDeposits(string $text): array
    {
        $deposits = [];
        $section = $this->extractSection($text, 'Deposit\s+(?:Detail|Activity|Summary)', 'Chargeback|Adjustment|Reserve|Fee|End');
        if (empty($section)) return $deposits;

        $lines = preg_split('/\r?\n/', $section);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || preg_match('/^\s*(Day|Date|Total|Page)/i', $line)) continue;

            if (preg_match('/^\s*(\d{1,2})\s+(\d{6,20})\s+(\d+)\s+\$?([\d,\.]+)\s+\$?([\d,\.]+)\s+\$?([\d,\.]+)\s+\$?([\-\d,\.]+)/', $line, $m)) {
                $deposits[] = [
                    'deposit_day' => (int) $m[1],
                    'reference_number' => $m[2],
                    'tran_code' => null,
                    'plan_code' => null,
                    'sales_count' => (int) $m[3],
                    'sales_amount' => $this->parseMoney($m[4]),
                    'credits_amount' => $this->parseMoney($m[5]),
                    'discount_paid' => $this->parseMoney($m[6]),
                    'net_deposit' => $this->parseMoney($m[7]),
                    'raw_row_text' => $line,
                ];
            } elseif (preg_match('/^\s*(\d{1,2})\s+(\d{6,20})\s+.*?([\-\d,\.]+)\s*$/', $line, $m)) {
                $deposits[] = [
                    'deposit_day' => (int) $m[1],
                    'reference_number' => $m[2],
                    'tran_code' => null, 'plan_code' => null,
                    'sales_count' => 0, 'sales_amount' => 0,
                    'credits_amount' => 0, 'discount_paid' => 0,
                    'net_deposit' => $this->parseMoney($m[3]),
                    'raw_row_text' => $line,
                ];
            }
        }
        return $deposits;
    }

    protected function parseChargebacks(string $text): array
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

            // day  ref  tran_code  card  reason  amount
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
            // day  ref  tran_code  amount
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
            // day  ref  amount (no tran code)
            elseif (preg_match('/^\s*(\d{1,2})\s+(\d{6,20})\s+\$?([\-\d,\.]+)/', $line, $m)) {
                $chargebacks[] = [
                    'chargeback_day' => (int) $m[1], 'reference_number' => $m[2], 'tran_code' => null,
                    'amount' => abs($this->parseMoney($m[3])), 'event_type' => 'chargeback',
                    'recovered_flag' => false,
                    'card_brand' => null, 'reason_code' => null, 'case_number' => null,
                    'raw_row_text' => $line,
                ];
            }
            // MM/DD  ref  tran_code  amount
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
            // MM/DD  ref  amount
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

    protected function parseReserves(string $text): array
    {
        $reserves = [];
        $section = $this->extractSection($text, 'Reserve', 'Fee|Summary|End');
        if (empty($section)) {
            if (preg_match('/Reserve\s+(?:Ending|Balance)\s*[:\-]?\s*\$?([\d,\.]+)/i', $text, $m)) {
                $reserves[] = ['reserve_day' => null, 'reserve_amount' => 0, 'release_amount' => 0,
                    'running_balance' => $this->parseMoney($m[1]), 'raw_row_text' => $m[0]];
            }
            return $reserves;
        }

        $lines = preg_split('/\r?\n/', $section);
        foreach ($lines as $line) {
            if (preg_match('/^\s*(\d{1,2})\s+\$?([\d,\.]+)\s+\$?([\d,\.]+)\s+\$?([\d,\.]+)/', trim($line), $m)) {
                $reserves[] = [
                    'reserve_day' => (int) $m[1],
                    'reserve_amount' => $this->parseMoney($m[2]),
                    'release_amount' => $this->parseMoney($m[3]),
                    'running_balance' => $this->parseMoney($m[4]),
                    'raw_row_text' => $line,
                ];
            }
        }
        return $reserves;
    }

    protected function parseFees(string $text): array
    {
        $fees = [];
        $section = $this->extractSection($text, 'Fee\s+(?:Detail|Summary|Breakdown)', 'Summary|Totals|End');
        if (empty($section)) return $fees;

        $lines = preg_split('/\r?\n/', $section);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || preg_match('/^\s*(Description|Fee|Total|Page)/i', $line)) continue;

            if (preg_match('/^\s*(.{10,50}?)\s+(\d+)\s+\$?([\d,\.]+)\s+\$?([\-\d,\.]+)\s*$/', $line, $m)) {
                $desc = trim($m[1]);
                $fees[] = [
                    'fee_description' => $desc,
                    'fee_category' => \App\Models\StatementFee::categorizeDescription($desc),
                    'quantity' => (int) $m[2],
                    'basis_amount' => $this->parseMoney($m[3]),
                    'rate' => null,
                    'fee_total' => $this->parseMoney($m[4]),
                    'raw_row_text' => $line,
                ];
            } elseif (preg_match('/^\s*(.{10,50}?)\s+\$?([\-\d,\.]+)\s*$/', $line, $m)) {
                $desc = trim($m[1]);
                if (preg_match('/^(total|subtotal)/i', $desc)) continue;
                $fees[] = [
                    'fee_description' => $desc,
                    'fee_category' => \App\Models\StatementFee::categorizeDescription($desc),
                    'quantity' => 0, 'basis_amount' => 0, 'rate' => null,
                    'fee_total' => $this->parseMoney($m[2]),
                    'raw_row_text' => $line,
                ];
            }
        }
        return $fees;
    }

    protected function parseSummaryTotals(string $text, array $parsed): array
    {
        $summary = $this->emptyResult()['summary'];
        $fields = [
            'gross_sales' => '/Gross\s+(?:Sales|Volume)\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'credits' => '/Credits?\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'net_sales' => '/Net\s+Sales\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'discount_due' => '/Discount\s+Due\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'discount_paid' => '/Discount\s+Paid\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'fees_due' => '/Fees?\s+Due\s*[:\-]?\s*\$?([\d,\.]+)/i',
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
