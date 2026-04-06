<?php

namespace App\Support\Phone;

/**
 * Normalizes phone numbers to E.164 format.
 * No external dependency — works with standard US/international patterns.
 */
class PhoneNumberNormalizer
{
    /**
     * Normalize a raw phone string to E.164.
     * Returns null if the number cannot be normalized.
     */
    public static function normalize(?string $raw, string $defaultCountry = 'US'): ?string
    {
        if (! $raw) return null;

        // Strip everything except digits and leading +
        $cleaned = preg_replace('/[^\d+]/', '', trim($raw));

        if ($cleaned === '' || $cleaned === '+') return null;

        // Already E.164 format
        if (str_starts_with($cleaned, '+') && strlen($cleaned) >= 11 && strlen($cleaned) <= 16) {
            return $cleaned;
        }

        // Remove leading + for processing
        $digits = ltrim($cleaned, '+');

        // US/CA: 10 digits → +1
        if ($defaultCountry === 'US' || $defaultCountry === 'CA') {
            if (strlen($digits) === 10) {
                return '+1' . $digits;
            }
            if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
                return '+' . $digits;
            }
        }

        // If starts with country code already (11+ digits)
        if (strlen($digits) >= 11 && strlen($digits) <= 15) {
            return '+' . $digits;
        }

        // Cannot determine — return null (invalid)
        return null;
    }

    /**
     * Format for display: (407) 555-1212
     */
    public static function formatNational(?string $e164): ?string
    {
        if (! $e164) return null;

        $digits = ltrim($e164, '+');

        // US/CA format
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $area = substr($digits, 1, 3);
            $first = substr($digits, 4, 3);
            $last = substr($digits, 7, 4);
            return "({$area}) {$first}-{$last}";
        }

        return $e164; // Return as-is for non-US
    }

    /**
     * Extract country code from E.164 number.
     */
    public static function countryCode(?string $e164): string
    {
        if (! $e164) return 'US';
        $digits = ltrim($e164, '+');
        if (str_starts_with($digits, '1') && strlen($digits) === 11) return 'US';
        if (str_starts_with($digits, '44')) return 'GB';
        if (str_starts_with($digits, '52')) return 'MX';
        return 'US'; // Default
    }
}
