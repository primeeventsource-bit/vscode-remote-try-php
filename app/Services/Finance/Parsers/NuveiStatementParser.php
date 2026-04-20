<?php

namespace App\Services\Finance\Parsers;

/**
 * Full Nuvei/Pivotal-style merchant statement parser.
 * Handles the standard Nuvei monthly statement PDF format with sections:
 * Header, Plan Summary, Deposits, Chargebacks, Reserves, Fees, Totals.
 */
class NuveiStatementParser extends BaseStatementParser
{
    public function version(): string { return '2.0.0'; }
    public function slug(): string { return 'nuvei'; }

    protected function parseHeader(string $text): array
    {
        $header = $this->emptyResult()['header'];
        $header['processor_name'] = 'Nuvei';

        // Merchant name — typically first prominent line or after "Merchant:" label
        if (preg_match('/(?:Merchant\s*(?:Name)?|DBA)\s*[:\-]?\s*(.+)/i', $text, $m)) {
            $header['merchant_name'] = trim($m[1]);
        } elseif (preg_match('/^([A-Z][A-Z\s&\'\.\-]{2,40})\s*$/m', $text, $m)) {
            $header['merchant_name'] = trim($m[1]);
        }

        // Merchant/MID number
        if (preg_match('/(?:Merchant\s*(?:Number|#|No)|MID)\s*[:\-]?\s*(\d[\d\-\*xX\.]+)/i', $text, $m)) {
            $header['merchant_number'] = trim($m[1]);
        }

        // Association number
        if (preg_match('/(?:Association|Chain)\s*(?:Number|#|No)?\s*[:\-]?\s*(\d[\d\-]+)/i', $text, $m)) {
            $header['association_number'] = trim($m[1]);
        }

        // Statement month
        $header['statement_month'] = $this->parseStatementMonth($text);

        // Routing last 4
        $header['routing_last4'] = $this->extractLast4($text, 'Routing');
        // Deposit account last 4
        $header['deposit_account_last4'] = $this->extractLast4($text, 'Deposit Account')
            ?? $this->extractLast4($text, 'DDA')
            ?? $this->extractLast4($text, 'Account');

        $this->log('info', 'header', 'Header parsed', $header);
        return $header;
    }

    protected function parsePlanSummary(string $text): array
    {
        $plans = [];

        // Try to find Plan Summary section
        $section = $this->extractSection(
            $text,
            'Plan\s+Summary|Card\s+Type\s+Summary|Plan\s+Code\s+Summary',
            'Deposit\s+Detail|Deposit\s+Activity|Chargeback|Fee\s+Detail|End\s+of'
        );

        if (empty($section)) {
            $this->log('warning', 'plan_summary', 'Plan Summary section not found');
            return $plans;
        }

        // Common Nuvei plan summary row pattern:
        // VS  |  VISA  |  count  |  sales_amount  |  credit_count  |  credit_amount  |  net_sales  |  disc_%  |  disc_due
        $rowPattern = '/^\s*([A-Z]{2,4})\s+(\d+)\s+([\d,\.]+)\s+(\d+)\s+([\d,\.]+)\s+([\d,\.]+)\s+([\d\.]+)\s*%?\s+([\d,\.]+)/';

        // Also try simpler format: brand  count  amount  credits  net  rate  discount
        $simplePattern = '/^\s*([A-Z]{2,10}(?:\s+[A-Z]{2,10})?)\s+(\d+)\s+\$?([\d,\.]+)\s+\$?([\d,\.]+)\s+\$?([\d,\.]+)/';

        $rows = $this->parseTableRows($section, $rowPattern);
        if (empty($rows)) {
            $rows = $this->parseTableRows($section, $simplePattern);
        }

        foreach ($rows as $row) {
            $brand = trim($row[1]);
            $salesCount = (int) ($row[2] ?? 0);
            $salesAmount = $this->parseMoney($row[3] ?? '0');
            $creditCount = isset($row[4]) ? (int) $row[4] : 0;
            $creditAmount = isset($row[5]) ? $this->parseMoney($row[5]) : 0;
            $netSales = isset($row[6]) ? $this->parseMoney($row[6]) : ($salesAmount - $creditAmount);
            $discountRate = isset($row[7]) ? (float) $row[7] : 0;
            $discountDue = isset($row[8]) ? $this->parseMoney($row[8]) : 0;

            $plans[] = [
                'card_brand' => $brand,
                'plan_code' => null,
                'sales_count' => $salesCount,
                'sales_amount' => $salesAmount,
                'credit_count' => $creditCount,
                'credit_amount' => $creditAmount,
                'net_sales' => $netSales,
                'average_ticket' => $salesCount > 0 ? round($salesAmount / $salesCount, 2) : 0,
                'discount_rate' => $discountRate,
                'discount_due' => $discountDue,
            ];
        }

        $this->log('info', 'plan_summary', 'Parsed ' . count($plans) . ' plan rows');
        return $plans;
    }

    protected function parseDeposits(string $text): array
    {
        $deposits = [];

        $section = $this->extractSection(
            $text,
            'Deposit\s+Detail|Deposit\s+Activity|Daily\s+Deposit',
            'Chargeback|Adjustment|Reserve|Fee\s+Detail|End\s+of'
        );

        if (empty($section)) {
            $this->log('warning', 'deposits', 'Deposit section not found');
            return $deposits;
        }

        // Pattern: day/date  ref#  tran_code  plan  sales_count  sales_amt  credits  discount  net_deposit
        $patterns = [
            // Full row: day  reference  tran  plan  count  sales  credits  discount  net
            '/^\s*(\d{1,2})\s+(\d{6,20})\s+([A-Z]{1,4})\s+([A-Z]{2,6})?\s*(\d+)\s+([\d,\.]+)\s+([\d,\.]+)\s+([\d,\.]+)\s+([\-\d,\.]+)/',
            // Simpler: day  reference  count  sales  credits  discount  net
            '/^\s*(\d{1,2})\s+(\d{6,20})\s+(\d+)\s+\$?([\d,\.]+)\s+\$?([\d,\.]+)\s+\$?([\d,\.]+)\s+\$?([\-\d,\.]+)/',
            // Minimal: day  reference  net_deposit
            '/^\s*(\d{1,2})\s+(\d{6,20})\s+.*?([\-\d,\.]+)\s*$/',
        ];

        $lines = preg_split('/\r?\n/', $section);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            // Skip headers and totals
            if (preg_match('/^\s*(Day|Date|Total|Subtotal|Page|Continued)/i', $line)) continue;

            $matched = false;
            // Try patterns in order of specificity
            if (preg_match($patterns[0], $line, $m)) {
                $deposits[] = [
                    'deposit_day' => (int) $m[1],
                    'reference_number' => $m[2],
                    'tran_code' => $m[3],
                    'plan_code' => !empty($m[4]) ? $m[4] : null,
                    'sales_count' => (int) $m[5],
                    'sales_amount' => $this->parseMoney($m[6]),
                    'credits_amount' => $this->parseMoney($m[7]),
                    'discount_paid' => $this->parseMoney($m[8]),
                    'net_deposit' => $this->parseMoney($m[9]),
                    'raw_row_text' => $line,
                ];
                $matched = true;
            } elseif (preg_match($patterns[1], $line, $m)) {
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
                $matched = true;
            } elseif (preg_match($patterns[2], $line, $m)) {
                $deposits[] = [
                    'deposit_day' => (int) $m[1],
                    'reference_number' => $m[2],
                    'tran_code' => null,
                    'plan_code' => null,
                    'sales_count' => 0,
                    'sales_amount' => 0,
                    'credits_amount' => 0,
                    'discount_paid' => 0,
                    'net_deposit' => $this->parseMoney($m[3]),
                    'raw_row_text' => $line,
                ];
                $matched = true;
            }

            if (!$matched && preg_match('/\d/', $line) && !preg_match('/^\s*\d{1,2}\s*$/', $line)) {
                $this->log('warning', 'deposits', 'Unmatched deposit row', ['line' => $line]);
            }
        }

        $this->log('info', 'deposits', 'Parsed ' . count($deposits) . ' deposit rows');
        return $deposits;
    }

    protected function parseChargebacks(string $text): array
    {
        $chargebacks = [];

        // Try multiple section header patterns in order of specificity
        $sectionPatterns = [
            'Chargeback\s+(?:Detail|Activity|Summary)',
            'Chargeback',
            'Dispute\s+(?:Detail|Activity|Summary)',
            'Adjustment\s+(?:Detail|Activity)',
            'CB\s+(?:Detail|Activity|Summary)',
            'Retrieval|Representment',
        ];

        $section = '';
        foreach ($sectionPatterns as $sp) {
            $section = $this->extractSection($text, $sp, 'Reserve|Fee\s+Detail|Fee\s+Summary|Totals|End\s+of|Page\s+\d+\s+of');
            if (!empty($section)) break;
        }

        if (empty($section)) {
            // Fallback: scan full text for chargeback-like rows
            $chargebacks = $this->fallbackChargebackScan($text);
            if (!empty($chargebacks)) {
                $this->log('info', 'chargebacks', 'Chargebacks found via fallback full-text scan: ' . count($chargebacks));
            } else {
                $this->log('info', 'chargebacks', 'No chargeback section found (may not have chargebacks)');
            }
            return $chargebacks;
        }

        // Pattern: day  ref#  tran_code  amount
        $patterns = [
            // Full: day  ref  tran_code  card_brand  reason_code  amount
            '/^\s*(\d{1,2})\s+(\d{6,20})\s+([A-Z]{1,4})\s+([A-Z]{2,6})?\s*(?:(\d{2,6})\s+)?\$?([\-\d,\.]+)/',
            // Standard: day  ref  tran_code  amount
            '/^\s*(\d{1,2})\s+(\d{6,20})\s+([A-Z]{1,4})\s+\$?([\-\d,\.]+)/',
            // Minimal: day  ref  amount
            '/^\s*(\d{1,2})\s+(\d{6,20})\s+\$?([\-\d,\.]+)/',
            // Date format: MM/DD  ref  tran_code  amount
            '/^\s*(\d{1,2})\/\d{1,2}\s+(\d{6,20})\s+([A-Z]{1,4})\s+\$?([\-\d,\.]+)/',
            // Date format: MM/DD  ref  amount
            '/^\s*(\d{1,2})\/\d{1,2}\s+(\d{6,20})\s+\$?([\-\d,\.]+)/',
        ];

        $lines = preg_split('/\r?\n/', $section);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            if (preg_match('/^\s*(Day|Date|Total|Subtotal|Page|Section|Number|Ref)/i', $line)) continue;

            $matched = false;
            // Full pattern with card brand + reason code
            if (preg_match($patterns[0], $line, $m) && isset($m[6])) {
                $eventType = $this->mapTranCodeToEventType($m[3]);
                $chargebacks[] = [
                    'chargeback_day' => (int) $m[1],
                    'reference_number' => $m[2],
                    'tran_code' => $m[3],
                    'amount' => abs($this->parseMoney($m[6])),
                    'event_type' => $eventType,
                    'recovered_flag' => in_array($eventType, ['reversal', 'representment_credit']),
                    'card_brand' => !empty($m[4]) ? $m[4] : null,
                    'reason_code' => !empty($m[5]) ? $m[5] : null,
                    'case_number' => null,
                    'raw_row_text' => $line,
                ];
                $matched = true;
            }
            // Standard: day ref tran_code amount
            if (!$matched && preg_match($patterns[1], $line, $m)) {
                $eventType = $this->mapTranCodeToEventType($m[3]);
                $chargebacks[] = [
                    'chargeback_day' => (int) $m[1],
                    'reference_number' => $m[2],
                    'tran_code' => $m[3],
                    'amount' => abs($this->parseMoney($m[4])),
                    'event_type' => $eventType,
                    'recovered_flag' => in_array($eventType, ['reversal', 'representment_credit']),
                    'card_brand' => null,
                    'reason_code' => null,
                    'case_number' => null,
                    'raw_row_text' => $line,
                ];
                $matched = true;
            }
            // Minimal: day ref amount
            if (!$matched && preg_match($patterns[2], $line, $m)) {
                $chargebacks[] = [
                    'chargeback_day' => (int) $m[1],
                    'reference_number' => $m[2],
                    'tran_code' => null,
                    'amount' => abs($this->parseMoney($m[3])),
                    'event_type' => 'chargeback',
                    'recovered_flag' => false,
                    'card_brand' => null,
                    'reason_code' => null,
                    'case_number' => null,
                    'raw_row_text' => $line,
                ];
                $matched = true;
            }
            // Date format MM/DD with tran code
            if (!$matched && preg_match($patterns[3], $line, $m)) {
                $eventType = $this->mapTranCodeToEventType($m[3]);
                $chargebacks[] = [
                    'chargeback_day' => (int) $m[1],
                    'reference_number' => $m[2],
                    'tran_code' => $m[3],
                    'amount' => abs($this->parseMoney($m[4])),
                    'event_type' => $eventType,
                    'recovered_flag' => in_array($eventType, ['reversal', 'representment_credit']),
                    'card_brand' => null,
                    'reason_code' => null,
                    'case_number' => null,
                    'raw_row_text' => $line,
                ];
                $matched = true;
            }
            // Date format MM/DD minimal
            if (!$matched && preg_match($patterns[4], $line, $m)) {
                $chargebacks[] = [
                    'chargeback_day' => (int) $m[1],
                    'reference_number' => $m[2],
                    'tran_code' => null,
                    'amount' => abs($this->parseMoney($m[3])),
                    'event_type' => 'chargeback',
                    'recovered_flag' => false,
                    'card_brand' => null,
                    'reason_code' => null,
                    'case_number' => null,
                    'raw_row_text' => $line,
                ];
                $matched = true;
            }

            if (!$matched && preg_match('/\d/', $line) && !preg_match('/^\s*\d{1,2}\s*$/', $line)) {
                $this->log('warning', 'chargebacks', 'Unmatched chargeback row', ['line' => $line]);
            }
        }

        $this->log('info', 'chargebacks', 'Parsed ' . count($chargebacks) . ' chargeback rows');
        return $chargebacks;
    }

    protected function parseReserves(string $text): array
    {
        $reserves = [];

        $section = $this->extractSection(
            $text,
            'Reserve\s+(?:Detail|Activity|Fund|Summary)',
            'Fee\s+Detail|Summary|Totals|End\s+of'
        );

        if (empty($section)) {
            // Try to get reserve ending balance from summary
            if (preg_match('/Reserve\s+(?:Ending|Balance)\s*[:\-]?\s*\$?([\d,\.]+)/i', $text, $m)) {
                $reserves[] = [
                    'reserve_day' => null,
                    'reserve_amount' => 0,
                    'release_amount' => 0,
                    'running_balance' => $this->parseMoney($m[1]),
                    'raw_row_text' => $m[0],
                ];
            }
            return $reserves;
        }

        // Pattern: day  reserve_held  reserve_released  running_balance
        $pattern = '/^\s*(\d{1,2})\s+\$?([\d,\.]+)\s+\$?([\d,\.]+)\s+\$?([\d,\.]+)/';

        $lines = preg_split('/\r?\n/', $section);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || preg_match('/^\s*(Day|Date|Total|Page)/i', $line)) continue;

            if (preg_match($pattern, $line, $m)) {
                $reserves[] = [
                    'reserve_day' => (int) $m[1],
                    'reserve_amount' => $this->parseMoney($m[2]),
                    'release_amount' => $this->parseMoney($m[3]),
                    'running_balance' => $this->parseMoney($m[4]),
                    'raw_row_text' => $line,
                ];
            }
        }

        $this->log('info', 'reserves', 'Parsed ' . count($reserves) . ' reserve rows');
        return $reserves;
    }

    protected function parseFees(string $text): array
    {
        $fees = [];

        $section = $this->extractSection(
            $text,
            'Fee\s+Detail|Fee\s+Summary|Itemized\s+Fees|Other\s+Fees|Fee\s+Breakdown',
            'Summary|Totals|End\s+of|Page\s+\d'
        );

        if (empty($section)) {
            $this->log('warning', 'fees', 'Fee section not found');
            return $fees;
        }

        // Pattern: description  qty  basis_amount  fee_total
        $patterns = [
            '/^\s*(.{10,50}?)\s+(\d+)\s+\$?([\d,\.]+)\s+\$?([\-\d,\.]+)\s*$/',
            '/^\s*(.{10,50}?)\s+\$?([\-\d,\.]+)\s*$/',
        ];

        $lines = preg_split('/\r?\n/', $section);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || preg_match('/^\s*(Description|Fee|Total|Page|Subtotal)/i', $line)) continue;

            if (preg_match($patterns[0], $line, $m)) {
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
            } elseif (preg_match($patterns[1], $line, $m)) {
                $desc = trim($m[1]);
                // Skip if description looks like a header
                if (preg_match('/^(total|subtotal|page)/i', $desc)) continue;
                $fees[] = [
                    'fee_description' => $desc,
                    'fee_category' => \App\Models\StatementFee::categorizeDescription($desc),
                    'quantity' => 0,
                    'basis_amount' => 0,
                    'rate' => null,
                    'fee_total' => $this->parseMoney($m[2]),
                    'raw_row_text' => $line,
                ];
            }
        }

        $this->log('info', 'fees', 'Parsed ' . count($fees) . ' fee rows');
        return $fees;
    }

    protected function parseSummaryTotals(string $text, array $parsed): array
    {
        $summary = $this->emptyResult()['summary'];

        // Try to extract from explicit summary section
        $patterns = [
            'gross_sales' => '/(?:Gross\s+(?:Sales|Volume|Processing))\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'credits' => '/(?:Credits?|Refunds?)\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'net_sales' => '/(?:Net\s+Sales)\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'discount_due' => '/(?:Discount\s+Due)\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'discount_paid' => '/(?:Discount\s+Paid)\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'fees_due' => '/(?:Fees?\s+Due)\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'fees_paid' => '/(?:Fees?\s+Paid)\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'amount_deducted' => '/(?:Amount\s+Deducted|Total\s+Deductions?)\s*[:\-]?\s*\$?([\d,\.]+)/i',
            'reserve_ending_balance' => '/(?:Reserve\s+(?:Ending|End)\s+Balance|Reserve\s+Balance)\s*[:\-]?\s*\$?([\d,\.]+)/i',
        ];

        foreach ($patterns as $field => $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $summary[$field] = $this->parseMoney($m[1]);
            }
        }

        // Calculate from rows if not found in summary
        if ($summary['total_deposits'] == 0 && !empty($parsed['deposits'])) {
            $summary['total_deposits'] = array_sum(array_column($parsed['deposits'], 'net_deposit'));
        }
        if ($summary['total_chargebacks'] == 0 && !empty($parsed['chargebacks'])) {
            $cbs = array_filter($parsed['chargebacks'], fn($cb) => $cb['event_type'] === 'chargeback');
            $summary['total_chargebacks'] = array_sum(array_column($cbs, 'amount'));
        }
        if ($summary['total_reversals'] == 0 && !empty($parsed['chargebacks'])) {
            $revs = array_filter($parsed['chargebacks'], fn($cb) => in_array($cb['event_type'], ['reversal', 'representment_credit']));
            $summary['total_reversals'] = array_sum(array_column($revs, 'amount'));
        }
        if ($summary['reserve_ending_balance'] == 0 && !empty($parsed['reserves'])) {
            $last = end($parsed['reserves']);
            $summary['reserve_ending_balance'] = $last['running_balance'] ?? 0;
        }

        // Compute net_sales if not found
        if ($summary['net_sales'] == 0 && $summary['gross_sales'] > 0) {
            $summary['net_sales'] = $summary['gross_sales'] - $summary['credits'];
        }

        return $summary;
    }
}
