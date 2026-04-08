<?php

namespace App\Services\Finance;

/**
 * Detects processor, statement type, MID info, and merchant details from uploaded file content.
 * Prioritizes processor names in headers/titles over mentions in transaction lines.
 */
class StatementFormatDetector
{
    // Processors ordered by priority — more specific first, generic last
    private const PROCESSOR_SIGNATURES = [
        // Specific processors (less likely to be false positives)
        'Nuevi' => ['nuevi'],
        'Authorize.Net' => ['authorize.net', 'authorizenet', 'auth.net'],
        'NMI' => ['network merchants', 'nmi gateway'],
        'USAePay' => ['usaepay', 'usa epay'],
        'Clearent' => ['clearent'],
        'Dharma' => ['dharma merchant'],
        'Helcim' => ['helcim'],
        'Paysafe' => ['paysafe'],
        'FluidPay' => ['fluidpay'],
        'PayTrace' => ['paytrace'],
        'Elavon' => ['elavon', 'converge'],
        'TSYS' => ['tsys', 'transfirst', 'cayan'],
        'Paymentech' => ['paymentech', 'chase merchant services'],
        'Global Payments' => ['global payments', 'heartland payment'],
        'First Data' => ['first data', 'fiserv'],
        'Worldpay' => ['worldpay', 'vantiv', 'fisglobal'],
        'Merrick Bank' => ['merrick bank'],
        'Priority Payment' => ['priority payment systems'],
        // Generic names — only match if they appear in processor/header context
        'Square' => ['squareup.com', 'square merchant'],
        'Stripe' => ['stripe.com', 'stripe, inc'],
        'PayPal' => ['paypal merchant', 'paypal here', 'braintree payments'],
    ];

    // These words appear in transaction lines, NOT as processor names
    private const PAYMENT_METHOD_WORDS = [
        'paypal', 'venmo', 'apple pay', 'google pay', 'cash app',
        'zelle', 'visa', 'mastercard', 'amex', 'discover',
    ];

    public static function detect(string $content, string $filename, string $mimeType): array
    {
        $lower = strtolower($content);
        $processor = null;
        $confidence = 0;
        $statementType = null;

        // 1. Processor detection — check header area first (first 500 chars)
        $headerArea = substr($lower, 0, 500);
        foreach (self::PROCESSOR_SIGNATURES as $name => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($headerArea, $pattern)) {
                    $processor = $name;
                    $confidence = 0.90;
                    break 2;
                }
            }
        }

        // If not found in header, check full content but skip payment method false positives
        if (!$processor) {
            foreach (self::PROCESSOR_SIGNATURES as $name => $patterns) {
                foreach ($patterns as $pattern) {
                    // Skip if the pattern is a common payment method word appearing in transaction context
                    if (in_array($pattern, self::PAYMENT_METHOD_WORDS)) continue;
                    if (str_contains($lower, $pattern)) {
                        $processor = $name;
                        $confidence = 0.75;
                        break 2;
                    }
                }
            }
        }

        // 2. Statement type
        if (str_contains($lower, 'merchant statement') || str_contains($lower, 'processing statement') || str_contains($lower, 'monthly statement')) {
            $statementType = 'monthly_statement';
        } elseif (str_contains($lower, 'chargeback report') || str_contains($lower, 'dispute report')) {
            $statementType = 'chargeback_report';
        } elseif (str_contains($lower, 'deposit report') || str_contains($lower, 'funding report')) {
            $statementType = 'deposit_report';
        } elseif (str_contains($lower, 'reserve report')) {
            $statementType = 'reserve_report';
        } elseif (str_contains($lower, 'transaction report') || str_contains($lower, 'batch report')) {
            $statementType = 'transaction_report';
        }

        // 3. MID number
        $midNumber = self::extractMidNumber($content);
        if ($midNumber) $confidence = max($confidence, 0.80);

        // 4. Merchant/business name (the company that owns the MID)
        $businessName = self::extractBusinessName($content);

        // 5. Descriptor (DBA — what shows on cardholder statements)
        $descriptor = self::extractDescriptor($content);

        // 6. Gateway
        $gatewayName = self::extractGateway($content);

        // 7. Currency
        $currency = self::extractCurrency($content);

        // 8. Date range
        $dateRange = self::detectDateRange($content);

        // 9. Account name = business name or descriptor or filename
        $accountName = $businessName ?? $descriptor ?? pathinfo($filename, PATHINFO_FILENAME);

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

    private static function extractMidNumber(string $content): ?string
    {
        $patterns = [
            '/(?:merchant\s*(?:id|#|number|no\.?)|mid)\s*[:\-=]?\s*(\d[\d\-]{4,20}\d)/i',
            '/(?:account\s*(?:id|#|number|no\.?))\s*[:\-=]?\s*(\d[\d\-]{4,20}\d)/i',
            '/\bMID[:\s]+(\d[\d\-]{4,20}\d)\b/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $m)) {
                return trim($m[1]);
            }
        }
        return null;
    }

    // Business name = the legal entity / company that owns the MID
    private static function extractBusinessName(string $content): ?string
    {
        $patterns = [
            '/(?:business\s*name|legal\s*name|company\s*name|merchant\s*name)\s*[:\-=]?\s*([A-Z][A-Za-z0-9\s&\',\.\-]{2,60})/i',
            '/(?:dba|doing\s*business\s*as)\s*[:\-=]?\s*([A-Z][A-Za-z0-9\s&\',\.\-]{2,60})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $m)) {
                $name = trim($m[1]);
                $name = preg_replace('/\s+(merchant|mid|account|statement|date|period|phone|fax|address).*$/i', '', $name);
                if (strlen($name) >= 3 && strlen($name) <= 100) return $name;
            }
        }
        return null;
    }

    // Descriptor = what shows on cardholder credit card statements
    private static function extractDescriptor(string $content): ?string
    {
        $patterns = [
            '/(?:descriptor|statement\s*descriptor|billing\s*descriptor)\s*[:\-=]?\s*([A-Z][A-Za-z0-9\s&\'\*\.\-]{2,40})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $m)) {
                $desc = trim($m[1]);
                if (strlen($desc) >= 2 && strlen($desc) <= 50) return $desc;
            }
        }
        return null;
    }

    private static function extractGateway(string $content): ?string
    {
        $lower = strtolower($content);
        $gateways = [
            'NMI' => ['nmi', 'network merchants inc'],
            'Authorize.Net' => ['authorize.net'],
            'USAePay' => ['usaepay'],
            'PayTrace' => ['paytrace'],
            'FluidPay' => ['fluidpay'],
            'Converge' => ['converge'],
            'Bambora' => ['bambora'],
        ];

        foreach ($gateways as $name => $patterns) {
            foreach ($patterns as $p) {
                if (str_contains($lower, $p)) return $name;
            }
        }
        return null;
    }

    private static function extractCurrency(string $content): ?string
    {
        if (preg_match('/(?:currency|curr\.?)\s*[:\-=]?\s*(USD|CAD|EUR|GBP|AUD)/i', $content, $m)) {
            return strtoupper($m[1]);
        }
        if (str_contains($content, '$')) return 'USD';
        return null;
    }

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
