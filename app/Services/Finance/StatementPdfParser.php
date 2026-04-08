<?php

namespace App\Services\Finance;

/**
 * PDF statement parser.
 * 1. Deterministic text parsing for known patterns
 * 2. AI-assisted extraction for anything deterministic misses
 * 3. Merges both results — deterministic wins on conflicts, AI fills gaps
 */
class StatementPdfParser
{
    public static function parse(string $filePath, string $textContent, array $detection): array
    {
        $deterministicResult = ['summary' => null, 'lines' => []];
        $aiResult = ['summary' => null, 'lines' => []];

        // 1. Try deterministic parsing first
        if (strlen($textContent) > 100) {
            $deterministicResult = self::parseFromText($textContent, $detection);
        }

        // 2. Always try AI extraction — it catches chargebacks, fees, reserves
        //    that deterministic parsing often misses in complex PDFs
        try {
            $aiResult = StatementAiExtractor::extract($textContent, basename($filePath), $detection);
        } catch (\Throwable $e) {
            report($e);
        }

        // 3. Merge results — deterministic lines + AI lines (deduped)
        return self::mergeResults($deterministicResult, $aiResult);
    }

    private static function mergeResults(array $deterministic, array $ai): array
    {
        // Use AI summary if deterministic didn't find one, or merge fields
        $summary = $deterministic['summary'] ?? $ai['summary'] ?? null;
        if ($deterministic['summary'] && $ai['summary']) {
            // Merge: deterministic values win, AI fills gaps
            foreach ($ai['summary'] as $key => $val) {
                if ($val !== null && !isset($summary[$key])) {
                    $summary[$key] = $val;
                }
            }
        }

        // Combine lines — deterministic first, then AI lines that don't look like duplicates
        $allLines = $deterministic['lines'] ?? [];
        $existingRefs = collect($allLines)->pluck('reference')->filter()->toArray();
        $existingDescs = collect($allLines)->map(fn($l) => ($l['date'] ?? '') . '|' . ($l['amount'] ?? ''))->toArray();

        foreach ($ai['lines'] ?? [] as $aiLine) {
            // Skip if we already have this reference
            if (!empty($aiLine['reference']) && in_array($aiLine['reference'], $existingRefs)) continue;

            // Skip if we already have same date+amount combo
            $key = ($aiLine['date'] ?? '') . '|' . ($aiLine['amount'] ?? '');
            if (in_array($key, $existingDescs) && $key !== '|') continue;

            // Mark AI-sourced lines
            $aiLine['confidence'] = min($aiLine['confidence'] ?? 0.75, 0.80);
            $allLines[] = $aiLine;
        }

        return [
            'summary' => $summary,
            'lines' => $allLines,
            'ai_source' => !empty($ai['ai_source']),
        ];
    }

    private static function parseFromText(string $content, array $detection): array
    {
        $lines = [];
        $summary = self::extractSummaryTotals($content);

        $textLines = explode("\n", $content);
        foreach ($textLines as $textLine) {
            $line = self::parseTextLine(trim($textLine), $detection);
            if ($line) $lines[] = $line;
        }

        return ['summary' => $summary ?: null, 'lines' => $lines];
    }

    private static function extractSummaryTotals(string $content): array
    {
        $summary = [];
        $patterns = [
            'gross_volume' => '/(?:gross|total)\s*(?:volume|sales|processing)\s*[:\$]?\s*\$?([\d,]+\.?\d*)/i',
            'net_volume' => '/(?:net)\s*(?:volume|sales|amount)\s*[:\$]?\s*\$?([\d,]+\.?\d*)/i',
            'refunds' => '/(?:total\s*)?refunds?\s*[:\$]?\s*\$?([\d,]+\.?\d*)/i',
            'chargebacks' => '/(?:total\s*)?chargebacks?\s*[:\$]?\s*\$?([\d,]+\.?\d*)/i',
            'fees' => '/(?:total\s*)?fees?\s*[:\$]?\s*\$?([\d,]+\.?\d*)/i',
            'reserves' => '/(?:total\s*)?reserves?\s*[:\$]?\s*\$?([\d,]+\.?\d*)/i',
            'payouts' => '/(?:total\s*)?(?:payouts?|deposits?|funding)\s*[:\$]?\s*\$?([\d,]+\.?\d*)/i',
            'ending_balance' => '/(?:ending|closing)\s*balance\s*[:\$]?\s*\$?([\d,]+\.?\d*)/i',
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $content, $m)) {
                $summary[$key] = (float) str_replace(',', '', $m[1]);
            }
        }

        if (preg_match('/(?:period|statement)\s*(?:date|range)?[:\s]+(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\s*(?:to|through|\-|–)\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i', $content, $m)) {
            try {
                $summary['start_date'] = (new \DateTime($m[1]))->format('Y-m-d');
                $summary['end_date'] = (new \DateTime($m[2]))->format('Y-m-d');
            } catch (\Throwable) {}
        }

        return $summary;
    }

    private static function parseTextLine(string $line, array $detection): ?array
    {
        if (strlen($line) < 10) return null;

        if (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\s+(.+?)\s+\$?([\d,]+\.?\d*)/', $line, $m)) {
            $description = trim($m[2]);
            $amount = (float) str_replace(',', '', $m[3]);
            $type = self::detectType($description);
            $isNegative = str_contains($line, '(') || str_contains(strtolower($description), 'refund') || str_contains(strtolower($description), 'chargeback');
            if ($isNegative && $amount > 0) $amount = -$amount;

            try { $date = (new \DateTime($m[1]))->format('Y-m-d'); } catch (\Throwable) { $date = null; }

            // Extract card brand if visible
            $cardBrand = null;
            if (preg_match('/\b(visa|mastercard|amex|discover|mc)\b/i', $description, $cm)) {
                $cardBrand = ucfirst(strtolower($cm[1]));
                if ($cardBrand === 'Mc') $cardBrand = 'Mastercard';
            }

            // Extract last4 if visible
            $last4 = null;
            if (preg_match('/\*{2,}(\d{4})\b/', $line, $lm)) {
                $last4 = $lm[1];
            }

            // Extract reason code for chargebacks
            $reasonCode = null;
            if ($type === 'chargeback' && preg_match('/(?:reason|code)\s*[:\-]?\s*([\d\.]+)/i', $line, $rm)) {
                $reasonCode = $rm[1];
            }

            return [
                'type' => $type,
                'date' => $date,
                'description' => $description,
                'amount' => $amount,
                'currency' => 'USD',
                'reference' => null,
                'card_brand' => $cardBrand,
                'last4' => $last4,
                'reason_code' => $reasonCode,
                'status' => null,
                'confidence' => 0.65,
                'raw' => ['line' => $line],
            ];
        }

        return null;
    }

    private static function detectType(string $description): string
    {
        $lower = strtolower($description);
        if (str_contains($lower, 'chargeback') || str_contains($lower, 'dispute') || str_contains($lower, 'retrieval')) return 'chargeback';
        if (str_contains($lower, 'refund') || str_contains($lower, 'credit') || str_contains($lower, 'return')) return 'refund';
        if (str_contains($lower, 'fee') || str_contains($lower, 'interchange') || str_contains($lower, 'assessment') || str_contains($lower, 'dues')) return 'fee';
        if (str_contains($lower, 'reserve hold') || str_contains($lower, 'holdback')) return 'reserve_hold';
        if (str_contains($lower, 'reserve release')) return 'reserve_release';
        if (str_contains($lower, 'payout') || str_contains($lower, 'deposit') || str_contains($lower, 'funding') || str_contains($lower, 'ach')) return 'payout';
        if (str_contains($lower, 'adjustment')) return 'adjustment';
        return 'transaction';
    }
}
