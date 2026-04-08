<?php

namespace App\Services\Finance;

/**
 * Detects processor, statement type, MID info, and merchant details from uploaded file content.
 * Uses deterministic pattern matching first, then AI-assisted extraction for messy files.
 */
class StatementFormatDetector
{
    private const PROCESSOR_SIGNATURES = [
        'Authorize.Net' => ['authorize.net', 'authorizenet', 'auth.net'],
        'NMI' => ['nmi', 'network merchants', 'gateway id'],
        'Square' => ['square', 'sq *', 'squareup'],
        'Stripe' => ['stripe', 'stripe.com'],
        'PayPal' => ['paypal', 'braintree'],
        'First Data' => ['first data', 'fiserv', 'clover'],
        'TSYS' => ['tsys', 'transfirst', 'cayan'],
        'Worldpay' => ['worldpay', 'vantiv', 'fisglobal'],
        'Elavon' => ['elavon', 'converge'],
        'Paysafe' => ['paysafe', 'neteller', 'skrill'],
        'iPayment' => ['ipayment', 'paysign'],
        'Paymentech' => ['paymentech', 'chase merchant'],
        'Global Payments' => ['global payments', 'heartland'],
        'Merrick Bank' => ['merrick bank', 'merrick'],
        'Priority Payment' => ['priority payment', 'priority'],
        'Clearent' => ['clearent'],
        'Dharma' => ['dharma merchant'],
        'Helcim' => ['helcim'],
    ];

    /**
     * Full detection — processor, MID, business name, descriptor, gateway, currency, dates.
     */
    public static function detect(string $content, string $filename, string $mimeType): array
    {
        $lower = strtolower($content);
        $processor = null;
        $confidence = 0;
        $statementType = null;

        // 1. Processor detection
        foreach (self::PROCESSOR_SIGNATURES as $name => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($lower, $pattern)) {
                    $processor = $name;
                    $confidence = 0.85;
                    break 2;
                }
            }
        }

        // 2. Statement type
        if (str_contains($lower, 'merchant statement') || str_contains($lower, 'processing statement')) {
            $statementType = 'monthly_statement';
        } elseif (str_contains($lower, 'chargeback') || str_contains($lower, 'dispute')) {
            $statementType = 'chargeback_report';
        } elseif (str_contains($lower, 'deposit') || str_contains($lower, 'funding')) {
            $statementType = 'deposit_report';
        } elseif (str_contains($lower, 'reserve')) {
            $statementType = 'reserve_report';
        } elseif (str_contains($lower, 'transaction') || str_contains($lower, 'batch')) {
            $statementType = 'transaction_report';
        }

        // 3. MID number — try multiple patterns
        $midNumber = self::extractMidNumber($content);
        if ($midNumber) $confidence = max($confidence, 0.80);

        // 4. Business / merchant name
        $businessName = self::extractBusinessName($content);

        // 5. Descriptor (DBA name)
        $descriptor = self::extractDescriptor($content);

        // 6. Gateway name
        $gatewayName = self::extractGateway($content);

        // 7. Currency
        $currency = self::extractCurrency($content);

        // 8. Date range
        $dateRange = self::detectDateRange($content);

        // 9. Account name (use business name or descriptor or filename)
        $accountName = $businessName ?? $descriptor ?? pathinfo($filename, PATHINFO_FILENAME);

        // Boost confidence from filename
        if (preg_match('/statement|report|monthly|settlement/i', $filename)) {
            $confidence = min($confidence + 0.05, 1.0);
        }

        return [
            'processor' => $processor,
            'statement_type' => $statementType,
            'mid_number' => $midNumber,
            'business_name' => $businessName,
            'descriptor' => $descriptor,
            'gateway_name' => $gatewayName,
            'account_name' => $accountName,
            'currency' => $currency ?? 'USD',
            'date_range' => $dateRange,
            'confidence' => round($confidence, 2),
            'mime_type' => $mimeType,
        ];
    }

    // ── MID Number Extraction ───────────────────────────

    private static function extractMidNumber(string $content): ?string
    {
        $patterns = [
            '/(?:merchant\s*(?:id|#|number|no\.?)|mid)\s*[:\-=]?\s*(\d{6,20})/i',
            '/(?:account\s*(?:id|#|number|no\.?))\s*[:\-=]?\s*(\d{6,20})/i',
            '/(?:merchant\s*account)\s*[:\-=]?\s*(\d{6,20})/i',
            '/\bMID[:\s]+(\d{6,20})\b/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $m)) {
                return trim($m[1]);
            }
        }

        return null;
    }

    // ── Business Name Extraction ────────────────────────

    private static function extractBusinessName(string $content): ?string
    {
        $patterns = [
            '/(?:merchant\s*(?:name|business)|business\s*name|legal\s*name|company\s*name)\s*[:\-=]?\s*([A-Z][A-Za-z0-9\s&\',\.\-]{2,60})/i',
            '/(?:merchant)\s*[:\-=]\s*([A-Z][A-Za-z0-9\s&\',\.\-]{2,60})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $m)) {
                $name = trim($m[1]);
                // Clean trailing garbage
                $name = preg_replace('/\s+(merchant|mid|account|statement|date|period).*$/i', '', $name);
                if (strlen($name) >= 3 && strlen($name) <= 100) {
                    return $name;
                }
            }
        }

        return null;
    }

    // ── Descriptor / DBA Extraction ─────────────────────

    private static function extractDescriptor(string $content): ?string
    {
        $patterns = [
            '/(?:dba|doing\s*business\s*as|descriptor)\s*[:\-=]?\s*([A-Z][A-Za-z0-9\s&\'\*\.\-]{2,40})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $m)) {
                $desc = trim($m[1]);
                if (strlen($desc) >= 2 && strlen($desc) <= 50) return $desc;
            }
        }

        return null;
    }

    // ── Gateway Detection ───────────────────────────────

    private static function extractGateway(string $content): ?string
    {
        $lower = strtolower($content);
        $gateways = [
            'NMI' => ['nmi', 'network merchants inc'],
            'Authorize.Net' => ['authorize.net', 'authorizenet'],
            'USAePay' => ['usaepay', 'usa epay'],
            'PayTrace' => ['paytrace'],
            'FluidPay' => ['fluidpay', 'fluid pay'],
            'Converge' => ['converge'],
            'Paysafe' => ['paysafe'],
            'Bambora' => ['bambora'],
        ];

        foreach ($gateways as $name => $patterns) {
            foreach ($patterns as $p) {
                if (str_contains($lower, $p)) return $name;
            }
        }

        return null;
    }

    // ── Currency Detection ──────────────────────────────

    private static function extractCurrency(string $content): ?string
    {
        if (preg_match('/(?:currency|curr\.?)\s*[:\-=]?\s*(USD|CAD|EUR|GBP|AUD)/i', $content, $m)) {
            return strtoupper($m[1]);
        }
        // Default from common dollar signs
        if (str_contains($content, '$')) return 'USD';
        return null;
    }

    // ── Date Range Detection ────────────────────────────

    private static function detectDateRange(string $content): ?array
    {
        if (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\s*(?:to|through|\-|–)\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i', $content, $m)) {
            return ['start' => $m[1], 'end' => $m[2]];
        }
        if (preg_match('/((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\w*\s+\d{1,2},?\s+\d{4})\s*(?:to|through|\-|–)\s*((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\w*\s+\d{1,2},?\s+\d{4})/i', $content, $m)) {
            return ['start' => $m[1], 'end' => $m[2]];
        }
        return null;
    }
}
