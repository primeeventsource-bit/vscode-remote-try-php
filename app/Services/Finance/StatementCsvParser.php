<?php

namespace App\Services\Finance;

/**
 * Deterministic CSV/text statement parser.
 * Handles common merchant statement CSV formats.
 */
class StatementCsvParser
{
    private const LINE_TYPE_KEYWORDS = [
        'chargeback' => ['chargeback', 'cb', 'dispute', 'representment'],
        'refund' => ['refund', 'credit', 'return'],
        'fee' => ['fee', 'discount', 'assessment', 'interchange', 'dues'],
        'reserve_hold' => ['reserve hold', 'holdback', 'reserve debit'],
        'reserve_release' => ['reserve release', 'reserve credit'],
        'payout' => ['payout', 'deposit', 'funding', 'settlement', 'ach'],
        'adjustment' => ['adjustment', 'correction', 'misc'],
    ];

    public static function parse(string $content, array $detection): array
    {
        $lines = [];
        $summary = [];
        $rows = self::parseCsvRows($content);

        if (empty($rows)) return ['summary' => null, 'lines' => []];

        $headers = $rows[0] ?? [];
        $headerMap = self::mapHeaders($headers);

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (count($row) < 2) continue; // Skip empty rows

            $line = self::parseRow($row, $headerMap, $detection);
            if ($line) $lines[] = $line;
        }

        // Try to build summary from parsed lines
        $summary = self::buildSummaryFromLines($lines);

        // If deterministic parsing got very few results, supplement with AI
        if (count($lines) < 3 && strlen($content) > 200) {
            try {
                $aiResult = StatementAiExtractor::extract($content, 'statement.csv', $detection);
                if (!empty($aiResult['lines'])) {
                    $existingKeys = collect($lines)->map(fn($l) => ($l['date'] ?? '') . '|' . ($l['amount'] ?? ''))->toArray();
                    foreach ($aiResult['lines'] as $aiLine) {
                        $key = ($aiLine['date'] ?? '') . '|' . ($aiLine['amount'] ?? '');
                        if (!in_array($key, $existingKeys) || $key === '|') {
                            $lines[] = $aiLine;
                        }
                    }
                    // Use AI summary if deterministic didn't produce one
                    if (!$summary && !empty($aiResult['summary'])) {
                        $summary = $aiResult['summary'];
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return ['summary' => $summary, 'lines' => $lines];
    }

    private static function parseCsvRows(string $content): array
    {
        $rows = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    private static function mapHeaders(array $headers): array
    {
        $map = [];
        foreach ($headers as $i => $header) {
            $h = strtolower(trim($header));
            if (str_contains($h, 'date') || str_contains($h, 'time')) $map['date'] = $i;
            if (str_contains($h, 'amount') || str_contains($h, 'total') || str_contains($h, 'volume')) $map['amount'] = $i;
            if (str_contains($h, 'description') || str_contains($h, 'detail') || str_contains($h, 'memo')) $map['description'] = $i;
            if (str_contains($h, 'reference') || str_contains($h, 'id') || str_contains($h, 'transaction')) $map['reference'] = $i;
            if (str_contains($h, 'type') || str_contains($h, 'category')) $map['type'] = $i;
            if (str_contains($h, 'status')) $map['status'] = $i;
            if (str_contains($h, 'card') || str_contains($h, 'brand')) $map['card_brand'] = $i;
            if (str_contains($h, 'last4') || str_contains($h, 'last 4')) $map['last4'] = $i;
            if (str_contains($h, 'customer') || str_contains($h, 'name')) $map['customer'] = $i;
            if (str_contains($h, 'reason') || str_contains($h, 'code')) $map['reason_code'] = $i;
        }
        return $map;
    }

    private static function parseRow(array $row, array $headerMap, array $detection): ?array
    {
        $description = trim($row[$headerMap['description'] ?? 0] ?? '');
        $amountRaw = $row[$headerMap['amount'] ?? 1] ?? '0';
        $amount = self::parseAmount($amountRaw);

        if ($amount === null && !$description) return null;

        $type = self::detectLineType($description, $row[$headerMap['type'] ?? 999] ?? '');
        $date = self::parseDate($row[$headerMap['date'] ?? 999] ?? null);
        $reference = trim($row[$headerMap['reference'] ?? 999] ?? '');

        $confidence = 0.80;
        if (!$date) $confidence -= 0.15;
        if ($amount === null) $confidence -= 0.20;
        if ($type === 'transaction') $confidence -= 0.05; // default type = less confident

        return [
            'type' => $type,
            'date' => $date,
            'description' => $description,
            'amount' => $amount ?? 0,
            'currency' => 'USD',
            'reference' => $reference ?: null,
            'status' => $row[$headerMap['status'] ?? 999] ?? null,
            'confidence' => max(0.1, round($confidence, 2)),
            'raw' => array_combine(range(0, count($row) - 1), $row),
        ];
    }

    private static function detectLineType(string $description, string $typeCol): string
    {
        $check = strtolower($description . ' ' . $typeCol);

        foreach (self::LINE_TYPE_KEYWORDS as $type => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($check, $kw)) return $type;
            }
        }

        return 'transaction';
    }

    private static function parseAmount(string $raw): ?float
    {
        $cleaned = preg_replace('/[^0-9.\-\(\)]/', '', $raw);
        if ($cleaned === '' || $cleaned === null) return null;
        // Handle (123.45) negative format
        if (str_starts_with($cleaned, '(') && str_ends_with($cleaned, ')')) {
            $cleaned = '-' . trim($cleaned, '()');
        }
        return is_numeric($cleaned) ? round((float) $cleaned, 2) : null;
    }

    private static function parseDate(?string $raw): ?string
    {
        if (!$raw) return null;
        $raw = trim($raw);
        try {
            $dt = new \DateTime($raw);
            return $dt->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function buildSummaryFromLines(array $lines): ?array
    {
        if (empty($lines)) return null;

        $txns = array_filter($lines, fn($l) => $l['type'] === 'transaction' && ($l['amount'] ?? 0) > 0);
        $refunds = array_filter($lines, fn($l) => $l['type'] === 'refund' || ($l['type'] === 'transaction' && ($l['amount'] ?? 0) < 0));
        $cbs = array_filter($lines, fn($l) => $l['type'] === 'chargeback');
        $fees = array_filter($lines, fn($l) => $l['type'] === 'fee');
        $reserves = array_filter($lines, fn($l) => in_array($l['type'], ['reserve_hold', 'reserve_release']));
        $payouts = array_filter($lines, fn($l) => $l['type'] === 'payout');

        $dates = array_filter(array_column($lines, 'date'));
        sort($dates);

        return [
            'start_date' => $dates[0] ?? null,
            'end_date' => end($dates) ?: null,
            'gross_volume' => array_sum(array_column($txns, 'amount')),
            'refunds' => abs(array_sum(array_column($refunds, 'amount'))),
            'chargebacks' => abs(array_sum(array_column($cbs, 'amount'))),
            'fees' => abs(array_sum(array_column($fees, 'amount'))),
            'reserves' => array_sum(array_column($reserves, 'amount')),
            'payouts' => array_sum(array_column($payouts, 'amount')),
        ];
    }
}
