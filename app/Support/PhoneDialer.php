<?php

namespace App\Support;

class PhoneDialer
{
    public static function normalize(?string $phone): ?string
    {
        if (!$phone) return null;
        $digits = preg_replace('/[^0-9+]/', '', $phone);
        if (strlen($digits) < 7) return null;

        // Strip extension
        $digits = preg_replace('/[xX].*$/', '', $digits);

        // US: 10 digits → +1
        if (strlen($digits) === 10 && !str_starts_with($digits, '+')) {
            $digits = '+1' . $digits;
        }
        // US: 11 digits starting with 1 → +1
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = '+' . $digits;
        }
        // Ensure + prefix
        if (!str_starts_with($digits, '+') && strlen($digits) >= 10) {
            $digits = '+' . $digits;
        }

        return $digits;
    }

    public static function extractExtension(?string $phone): ?string
    {
        if (!$phone) return null;
        if (preg_match('/[xX]\s*(\d+)/', $phone, $m)) return $m[1];
        return null;
    }

    public static function digitsOnly(?string $phone): ?string
    {
        if (!$phone) return null;
        return preg_replace('/[^0-9]/', '', $phone);
    }

    public static function telHref(string $phone, ?string $prefix = null): ?string
    {
        $n = self::normalize($phone);
        if (!$n) return null;
        return 'tel:' . ($prefix ?? '') . $n;
    }

    public static function sipHref(string $phone, ?string $domain = null, ?string $prefix = null): ?string
    {
        $n = self::normalize($phone);
        if (!$n) return null;
        $target = ($prefix ?? '') . ltrim($n, '+');
        return $domain ? "sip:{$target}@{$domain}" : "sip:{$target}";
    }

    public static function formattedDisplay(?string $phone): ?string
    {
        $n = self::normalize($phone);
        if (!$n) return $phone;
        $d = ltrim($n, '+');
        if (strlen($d) === 11 && str_starts_with($d, '1')) {
            return '+1 (' . substr($d, 1, 3) . ') ' . substr($d, 4, 3) . '-' . substr($d, 7);
        }
        return $n;
    }

    public static function isValid(?string $phone): bool
    {
        $n = self::normalize($phone);
        return $n !== null && strlen(preg_replace('/[^0-9]/', '', $n)) >= 10;
    }

    public static function generateHref(string $phone, string $mode = 'tel', ?string $domain = null, ?string $prefix = null): ?string
    {
        return match ($mode) {
            'sip' => self::sipHref($phone, null, $prefix),
            'sip_with_domain' => self::sipHref($phone, $domain, $prefix),
            default => self::telHref($phone, $prefix),
        };
    }
}
