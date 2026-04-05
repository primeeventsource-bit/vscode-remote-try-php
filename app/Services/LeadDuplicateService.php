<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadDuplicate;
use Illuminate\Support\Facades\DB;

class LeadDuplicateService
{
    /**
     * Check a single lead row against existing leads for duplicates.
     * Returns array of matches: [{lead_id, duplicate_type, duplicate_reason, matched_fields}]
     */
    public static function findDuplicatesForRow(array $row, ?int $excludeId = null): array
    {
        $matches = [];
        $phone1 = self::normalizePhone($row['phone1'] ?? '');
        $phone2 = self::normalizePhone($row['phone2'] ?? '');
        $email = strtolower(trim($row['email'] ?? ''));
        $ownerName = trim($row['owner_name'] ?? '');
        $lastName = self::extractLastName($ownerName);

        $query = Lead::query()->select(['id', 'owner_name', 'phone1', 'phone2', 'email', 'resort', 'city', 'st']);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        // Exact email match
        if ($email) {
            $emailMatches = (clone $query)->where(DB::raw('LOWER(TRIM(email))'), $email)->limit(5)->get();
            foreach ($emailMatches as $match) {
                $matchedFields = ['email'];
                $type = 'exact';
                $reason = 'Same email address';

                if ($lastName && self::lastNameMatches($match->owner_name, $lastName)) {
                    $matchedFields[] = 'last_name';
                    $reason = 'Same email + last name';
                }

                $matches[$match->id] = [
                    'lead_id' => $match->id,
                    'duplicate_type' => $type,
                    'duplicate_reason' => $reason,
                    'matched_fields' => $matchedFields,
                ];
            }
        }

        // Exact phone1 match
        if ($phone1 && strlen($phone1) >= 7) {
            $phoneMatches = (clone $query)
                ->where(function ($q) use ($phone1) {
                    $q->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone1, '-', ''), '(', ''), ')', ''), ' ', ''), '+', '') LIKE ?", ["%{$phone1}"])
                      ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone2, '-', ''), '(', ''), ')', ''), ' ', ''), '+', '') LIKE ?", ["%{$phone1}"]);
                })
                ->limit(5)->get();

            foreach ($phoneMatches as $match) {
                if (isset($matches[$match->id])) continue;

                $matchedFields = ['phone1'];
                $type = 'exact';
                $reason = 'Same phone number';

                if ($lastName && self::lastNameMatches($match->owner_name, $lastName)) {
                    $matchedFields[] = 'last_name';
                    $reason = 'Same phone + last name';
                }

                $matches[$match->id] = [
                    'lead_id' => $match->id,
                    'duplicate_type' => $type,
                    'duplicate_reason' => $reason,
                    'matched_fields' => $matchedFields,
                ];
            }
        }

        // Exact phone2 match
        if ($phone2 && strlen($phone2) >= 7 && $phone2 !== $phone1) {
            $phone2Matches = (clone $query)
                ->where(function ($q) use ($phone2) {
                    $q->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone1, '-', ''), '(', ''), ')', ''), ' ', ''), '+', '') LIKE ?", ["%{$phone2}"])
                      ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone2, '-', ''), '(', ''), ')', ''), ' ', ''), '+', '') LIKE ?", ["%{$phone2}"]);
                })
                ->limit(5)->get();

            foreach ($phone2Matches as $match) {
                if (isset($matches[$match->id])) continue;

                $matches[$match->id] = [
                    'lead_id' => $match->id,
                    'duplicate_type' => 'exact',
                    'duplicate_reason' => 'Same phone number (phone2)',
                    'matched_fields' => ['phone2'],
                ];
            }
        }

        // Possible: same name + same resort
        if ($ownerName && strlen($ownerName) >= 3) {
            $resort = trim($row['resort'] ?? '');
            if ($resort) {
                $nameResortMatches = (clone $query)
                    ->where('owner_name', $ownerName)
                    ->where('resort', $resort)
                    ->limit(5)->get();

                foreach ($nameResortMatches as $match) {
                    if (isset($matches[$match->id])) continue;

                    $matches[$match->id] = [
                        'lead_id' => $match->id,
                        'duplicate_type' => 'possible',
                        'duplicate_reason' => 'Same name + same resort',
                        'matched_fields' => ['owner_name', 'resort'],
                    ];
                }
            }

            // Possible: same name + same city/state
            $city = trim($row['city'] ?? '');
            $st = trim($row['st'] ?? '');
            if ($city && $st) {
                $nameCityMatches = (clone $query)
                    ->where('owner_name', $ownerName)
                    ->where('city', $city)
                    ->where('st', $st)
                    ->limit(5)->get();

                foreach ($nameCityMatches as $match) {
                    if (isset($matches[$match->id])) continue;

                    $matches[$match->id] = [
                        'lead_id' => $match->id,
                        'duplicate_type' => 'possible',
                        'duplicate_reason' => 'Same name + same city/state',
                        'matched_fields' => ['owner_name', 'city', 'st'],
                    ];
                }
            }
        }

        return array_values($matches);
    }

    /**
     * Record duplicate pair in the database (avoids A-B / B-A duplication).
     */
    public static function recordDuplicate(int $leadId, int $duplicateLeadId, string $type, string $reason, array $matchedFields): LeadDuplicate
    {
        $lower = min($leadId, $duplicateLeadId);
        $higher = max($leadId, $duplicateLeadId);

        return LeadDuplicate::firstOrCreate(
            ['lead_id' => $lower, 'duplicate_lead_id' => $higher],
            [
                'duplicate_type' => $type,
                'duplicate_reason' => $reason,
                'matched_fields' => $matchedFields,
                'detected_at' => now(),
                'review_status' => 'pending',
            ]
        );
    }

    /**
     * Run duplicate scan on a batch of existing leads (by IDs).
     */
    public static function scanLeadIds(array $leadIds, int $chunkSize = 100): int
    {
        $found = 0;
        $chunks = array_chunk($leadIds, $chunkSize);

        foreach ($chunks as $chunk) {
            $leads = Lead::whereIn('id', $chunk)->get();
            foreach ($leads as $lead) {
                $row = $lead->only(['owner_name', 'phone1', 'phone2', 'email', 'resort', 'city', 'st']);
                $duplicates = self::findDuplicatesForRow($row, $lead->id);
                foreach ($duplicates as $dup) {
                    self::recordDuplicate($lead->id, $dup['lead_id'], $dup['duplicate_type'], $dup['duplicate_reason'], $dup['matched_fields']);
                    $found++;
                }
            }
        }

        return $found;
    }

    private static function normalizePhone(?string $phone): string
    {
        if (!$phone) return '';
        return preg_replace('/[^0-9]/', '', $phone);
    }

    private static function extractLastName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName));
        return count($parts) > 1 ? strtolower(end($parts)) : '';
    }

    private static function lastNameMatches(string $existingName, string $lastName): bool
    {
        $existingLast = self::extractLastName($existingName);
        return $existingLast && $existingLast === $lastName;
    }
}
