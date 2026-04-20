<?php

namespace App\Services\Finance\Parsers;

use App\Models\ParserLog;

/**
 * Base class with shared extraction utilities for all processor parsers.
 * Each concrete parser overrides section-specific regex patterns.
 */
abstract class BaseStatementParser implements StatementParserInterface
{
    protected int $statementId;
    protected array $lines = [];
    protected array $warnings = [];

    /**
     * Empty normalized result template.
     */
    protected function emptyResult(): array
    {
        return [
            'header' => [
                'merchant_name' => null,
                'processor_name' => null,
                'merchant_number' => null,
                'association_number' => null,
                'routing_last4' => null,
                'deposit_account_last4' => null,
                'statement_month' => null,
                'currency' => 'USD',
            ],
            'summary' => [
                'gross_sales' => 0,
                'credits' => 0,
                'net_sales' => 0,
                'discount_due' => 0,
                'discount_paid' => 0,
                'fees_due' => 0,
                'fees_paid' => 0,
                'amount_deducted' => 0,
                'total_deposits' => 0,
                'total_chargebacks' => 0,
                'total_reversals' => 0,
                'reserve_ending_balance' => 0,
            ],
            'plan_summaries' => [],
            'deposits' => [],
            'chargebacks' => [],
            'reserves' => [],
            'fees' => [],
            'confidence' => 50,
        ];
    }

    public function parse(string $rawText, int $statementId): array
    {
        $this->statementId = $statementId;
        $this->warnings = [];

        // Clean page-break artifacts BEFORE any parsing
        $cleanText = $this->stripPageBreakArtifacts($rawText);
        $this->lines = preg_split('/\r?\n/', $cleanText);

        $result = $this->emptyResult();

        try {
            $result['header'] = $this->parseHeader($cleanText);
        } catch (\Throwable $e) {
            $this->log('error', 'header', 'Header parse failed: ' . $e->getMessage());
        }

        try {
            $result['plan_summaries'] = $this->parsePlanSummary($cleanText);
        } catch (\Throwable $e) {
            $this->log('error', 'plan_summary', 'Plan summary parse failed: ' . $e->getMessage());
        }

        try {
            $result['deposits'] = $this->parseDeposits($cleanText);
        } catch (\Throwable $e) {
            $this->log('error', 'deposits', 'Deposits parse failed: ' . $e->getMessage());
        }

        try {
            $result['chargebacks'] = $this->parseChargebacks($cleanText);
        } catch (\Throwable $e) {
            $this->log('error', 'chargebacks', 'Chargebacks parse failed: ' . $e->getMessage());
        }

        try {
            $result['reserves'] = $this->parseReserves($cleanText);
        } catch (\Throwable $e) {
            $this->log('error', 'reserves', 'Reserves parse failed: ' . $e->getMessage());
        }

        try {
            $result['fees'] = $this->parseFees($cleanText);
        } catch (\Throwable $e) {
            $this->log('error', 'fees', 'Fees parse failed: ' . $e->getMessage());
        }

        try {
            $result['summary'] = $this->parseSummaryTotals($cleanText, $result);
        } catch (\Throwable $e) {
            $this->log('error', 'totals', 'Summary totals parse failed: ' . $e->getMessage());
        }

        $result['confidence'] = $this->calculateConfidence($result);

        return $result;
    }

    // ── Abstract methods each processor must implement ──────
    abstract protected function parseHeader(string $text): array;
    abstract protected function parsePlanSummary(string $text): array;
    abstract protected function parseDeposits(string $text): array;
    abstract protected function parseChargebacks(string $text): array;
    abstract protected function parseReserves(string $text): array;
    abstract protected function parseFees(string $text): array;
    abstract protected function parseSummaryTotals(string $text, array $parsed): array;

    // ── Shared utility methods ──────────────────────────────

    /**
     * Strip page-break artifacts from pdftotext output.
     * Multi-page PDFs produce form feeds, re-printed headers/footers, page numbers,
     * and separator lines between pages. This merges them into one continuous document
     * so section extraction works seamlessly across page boundaries.
     */
    protected function stripPageBreakArtifacts(string $text): string
    {
        // Remove form feed characters (ASCII 12) — pdftotext inserts these between pages
        $text = str_replace("\f", "\n", $text);

        // Remove "Page X of Y" / "Page X" / "- X -" style page number lines
        $text = preg_replace('/^\s*Page\s+\d+\s*(of\s+\d+)?\s*$/mi', '', $text);
        $text = preg_replace('/^\s*-\s*\d+\s*-\s*$/m', '', $text);
        $text = preg_replace('/^\s*\d+\s*\/\s*\d+\s*$/m', '', $text);

        // Remove lines that are just dashes, underscores, equals, or asterisks (page separators)
        $text = preg_replace('/^\s*[\-=_\*]{15,}\s*$/m', '', $text);

        // Build fingerprint of the first page header (first 15 non-empty lines)
        // to detect and remove re-printed headers on subsequent pages
        $lines = preg_split('/\r?\n/', $text);
        $headerLines = [];
        $headerLimit = 15;
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $headerLines[] = $trimmed;
                if (count($headerLines) >= $headerLimit) break;
            }
        }

        // Patterns that indicate a re-printed page header (after the first occurrence)
        $headerPatterns = [
            '/^\s*Merchant\s+Statement/i',
            '/^\s*Statement\s+(?:Period|Date|Month|For)/i',
            '/^\s*Processing\s+Statement/i',
            '/^\s*Monthly\s+Statement/i',
            '/^\s*Account\s+Statement/i',
            '/^\s*Merchant\s+(?:Name|Number|#|No)\s*[:\-]/i',
            '/^\s*MID\s*[:\-]\s*[\d\*]/i',
        ];

        $cleaned = [];
        $lineCount = 0;
        $skipNext = 0;

        foreach ($lines as $line) {
            $lineCount++;
            $trimmed = trim($line);

            // If we're in a skip-after-header zone, skip continuation header lines
            if ($skipNext > 0) {
                $skipNext--;
                // But keep the line if it looks like actual data (starts with a number + reference)
                if (preg_match('/^\s*\d{1,2}\s+\d{6,}/', $trimmed)) {
                    $cleaned[] = $line;
                }
                continue;
            }

            // Collapse excessive blank lines
            if ($trimmed === '' && !empty($cleaned) && trim(end($cleaned)) === '') {
                continue;
            }

            // After line 25, detect re-printed page headers and skip them + their block
            if ($lineCount > 25) {
                $isRepeatedHeader = false;
                foreach ($headerPatterns as $hp) {
                    if (preg_match($hp, $trimmed)) {
                        $isRepeatedHeader = true;
                        break;
                    }
                }

                // Also check if this line is an exact duplicate of one of the first header lines
                if (!$isRepeatedHeader && strlen($trimmed) > 10) {
                    foreach (array_slice($headerLines, 0, 5) as $hl) {
                        if ($trimmed === $hl) {
                            $isRepeatedHeader = true;
                            break;
                        }
                    }
                }

                if ($isRepeatedHeader) {
                    // Skip this line and up to 4 following lines (typical header block)
                    $skipNext = 4;
                    continue;
                }
            }

            // Remove "Continued" / "Continued on next page" lines
            if (preg_match('/^\s*(Continued|Cont\'?d)(\s+on\s+next\s+page)?\s*\.{0,3}\s*$/i', $trimmed)) {
                continue;
            }

            // Remove column header rows that repeat on each page (e.g., "Day  Reference  Tran Code  Amount")
            // Only after line 25 (keep the first occurrence for section identification)
            if ($lineCount > 25 && preg_match('/^\s*(Day|Date)\s+(Ref|Reference)\s+/i', $trimmed)) {
                continue;
            }

            $cleaned[] = $line;
        }

        $result = implode("\n", $cleaned);

        // Collapse 3+ consecutive blank lines into 1
        $result = preg_replace('/\n{3,}/', "\n\n", $result);

        return $result;
    }

    /**
     * Extract a monetary value from text, handling commas, negatives, parentheses.
     */
    protected function parseMoney(string $value): float
    {
        $value = trim($value);
        $negative = false;

        // Handle parenthetical negatives: (1,234.56) or -1,234.56
        if (preg_match('/^\((.+)\)$/', $value, $m)) {
            $value = $m[1];
            $negative = true;
        }
        if (str_starts_with($value, '-')) {
            $negative = true;
            $value = ltrim($value, '-');
        }

        $value = str_replace(['$', ',', ' '], '', $value);
        $amount = (float) $value;

        return $negative ? -$amount : $amount;
    }

    /**
     * Try to extract a date-like month from text (e.g., "March 2025", "03/2025").
     */
    protected function parseStatementMonth(string $text): ?string
    {
        // "March 2025" or "MARCH 2025"
        if (preg_match('/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})\b/i', $text, $m)) {
            $monthNum = date('m', strtotime($m[1] . ' 1'));
            return $m[2] . '-' . $monthNum;
        }
        // "03/2025" or "3/2025"
        if (preg_match('/\b(\d{1,2})\s*\/\s*(\d{4})\b/', $text, $m)) {
            return $m[2] . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }
        // "2025-03"
        if (preg_match('/\b(\d{4})-(\d{2})\b/', $text, $m)) {
            return $m[1] . '-' . $m[2];
        }
        return null;
    }

    /**
     * Extract all lines between two section headers.
     * Handles sections that span across page breaks by skipping
     * re-printed instances of the same start header (continuation pages).
     */
    protected function extractSection(string $text, string $startPattern, string $endPattern): string
    {
        if (preg_match('/' . $startPattern . '/is', $text, $startMatch, PREG_OFFSET_CAPTURE)) {
            $startPos = $startMatch[0][1] + strlen($startMatch[0][0]);
            $remaining = substr($text, $startPos);

            if ($endPattern) {
                // Find the end pattern, but skip instances of the start pattern
                // (which are just re-printed section headers on continuation pages)
                $endPos = null;
                $searchFrom = 0;

                while (preg_match('/' . $endPattern . '/is', $remaining, $endMatch, PREG_OFFSET_CAPTURE, $searchFrom)) {
                    $candidatePos = $endMatch[0][1];
                    $candidateText = $endMatch[0][0];

                    // If this "end" match is actually a re-occurrence of the start pattern (continued section),
                    // skip it and keep looking
                    if (preg_match('/' . $startPattern . '/is', $candidateText)) {
                        $searchFrom = $candidatePos + strlen($candidateText);
                        continue;
                    }

                    $endPos = $candidatePos;
                    break;
                }

                if ($endPos !== null) {
                    return substr($remaining, 0, $endPos);
                }
            }
            return $remaining;
        }
        return '';
    }

    /**
     * Parse table rows from a section of text.
     * Splits lines and matches each against a row pattern.
     */
    protected function parseTableRows(string $sectionText, string $rowPattern): array
    {
        $rows = [];
        $lines = preg_split('/\r?\n/', $sectionText);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            if (preg_match($rowPattern, $line, $m)) {
                $rows[] = $m;
            }
        }

        return $rows;
    }

    /**
     * Extract last 4 digits from a masked account number.
     */
    protected function extractLast4(string $text, string $label): ?string
    {
        $pattern = '/' . preg_quote($label, '/') . '\s*[:\-]?\s*[\*xX\.]*(\d{4})/i';
        if (preg_match($pattern, $text, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Fallback: scan full text for chargeback-like rows when no section header is found.
     * Looks for lines containing known chargeback indicators (tran codes C, CB, B, RV, etc.)
     * followed by monetary amounts.
     */
    protected function fallbackChargebackScan(string $text): array
    {
        $chargebacks = [];
        $lines = preg_split('/\r?\n/', $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            // Skip obvious non-data lines
            if (preg_match('/^\s*(Day|Date|Total|Subtotal|Page|Section|Deposit|Plan|Fee|Reserve|Header|Merchant|Statement)/i', $line)) continue;

            // Look for lines with chargeback tran codes: C, CB, B, BR, RV, RC, REP, RR, RET
            if (preg_match('/^\s*(\d{1,2})\s+(\d{6,20})\s+(C|CB|B|BR|RV|RC|REP|RR|RET)\s+\$?([\-\d,\.]+)/i', $line, $m)) {
                $eventType = $this->mapTranCodeToEventType(strtoupper($m[3]));
                $chargebacks[] = [
                    'chargeback_day' => (int) $m[1],
                    'reference_number' => $m[2],
                    'tran_code' => strtoupper($m[3]),
                    'amount' => abs($this->parseMoney($m[4])),
                    'event_type' => $eventType,
                    'recovered_flag' => in_array($eventType, ['reversal', 'representment_credit']),
                    'card_brand' => null,
                    'reason_code' => null,
                    'case_number' => null,
                    'raw_row_text' => $line,
                ];
            }
        }

        return $chargebacks;
    }

    /**
     * Map transaction codes to event types.
     */
    protected function mapTranCodeToEventType(string $tranCode): string
    {
        $code = strtoupper(trim($tranCode));
        return match ($code) {
            'C', 'CB' => 'chargeback',
            'B', 'BR', 'RV' => 'reversal',
            'RC', 'REP' => 'representment_credit',
            'RR', 'RET' => 'retrieval_request',
            default => 'chargeback',
        };
    }

    /**
     * Calculate overall parse confidence based on populated fields.
     */
    protected function calculateConfidence(array $result): int
    {
        $score = 0;
        $checks = 0;

        // Header completeness
        $checks += 4;
        if (!empty($result['header']['merchant_name'])) $score++;
        if (!empty($result['header']['merchant_number'])) $score++;
        if (!empty($result['header']['statement_month'])) $score++;
        if (!empty($result['header']['processor_name'])) $score++;

        // Has deposits
        $checks += 2;
        if (!empty($result['deposits'])) $score += 2;

        // Has summary
        $checks += 2;
        if (($result['summary']['gross_sales'] ?? 0) > 0) $score += 2;

        // Has fees
        $checks++;
        if (!empty($result['fees'])) $score++;

        // Warnings reduce confidence
        $warningPenalty = count($this->warnings) * 3;

        return max(10, min(99, (int) round(($score / $checks) * 100) - $warningPenalty));
    }

    /**
     * Log a parser event.
     */
    protected function log(string $level, string $section, string $message, array $context = []): void
    {
        if ($level === 'warning') {
            $this->warnings[] = $message;
        }

        try {
            ParserLog::create([
                'statement_id' => $this->statementId,
                'level' => $level,
                'section' => $section,
                'message' => $message,
                'context' => !empty($context) ? $context : null,
            ]);
        } catch (\Throwable $e) {
            // Logging should never break parsing
        }
    }
}
