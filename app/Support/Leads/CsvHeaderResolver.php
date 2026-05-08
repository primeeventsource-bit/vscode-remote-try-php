<?php

namespace App\Support\Leads;

/**
 * Resolves a CSV header row to Lead model fields.
 *
 * Pure utility — no Eloquent, no Livewire state, no I/O. Lifted out of
 * App\Livewire\Leads in 2026-05 so it can be unit-tested directly without
 * spinning up a Livewire component.
 *
 * Header normalization: lowercased, non-alphanumeric stripped.
 * Examples:
 *   "Phone Number 1" → "phonenumber1"  → maps to `phone1`
 *   "County/State"   → "countystate"   → maps to `countystate` (combined; split downstream)
 */
final class CsvHeaderResolver
{
    /**
     * Maps each Lead column to a list of accepted CSV header synonyms (already normalized).
     * Adding a new synonym? Add it here only. Header matching is first-match-wins
     * across the full row, so put the more-specific synonym (e.g. `phone1`) earlier
     * in its own bucket and keep the broad one (e.g. `phone`) as a fallback.
     *
     * @var array<string, array<int, string>>
     */
    public const FIELD_SYNONYMS = [
        'resort'          => ['resort', 'resortname', 'property', 'club'],
        'owner_name'      => ['ownername', 'owner', 'name', 'fullname', 'primaryowner', 'owner1', 'ownerone'],
        'owner_name_2'    => ['ownername2', 'owner2', 'ownertwo', 'secondaryowner', 'coowner', 'spouse', 'jointowner'],
        'phone1'          => ['phone1', 'phonenumber1', 'phone', 'phonenumber', 'primaryphone', 'mobile', 'cell'],
        'phone2'          => ['phone2', 'phonenumber2', 'secondaryphone', 'altphone', 'altphonenumber'],
        'city'            => ['city', 'town'],
        'st'              => ['st', 'state'],
        'zip'             => ['zip', 'zipcode', 'postal', 'postalcode'],
        'resort_location' => ['resortlocation', 'location', 'resortcity', 'resortcitystate'],
        'email'           => ['email', 'emailaddress', 'mail'],
        'description'     => ['description', 'notes', 'note', 'comments', 'comment', 'memo', 'remarks'],
        // Combined fields split downstream in mapCsvRow():
        'countystate'     => ['countystate', 'county_state', 'countyandstate'],
    ];

    /**
     * Normalize a single header cell for matching.
     * Lowercase, strip non-alphanumeric, trim.
     */
    public static function normalize(string $cell): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $cell) ?? '');
    }

    /**
     * Build a [columnIndex => leadField] map from a header row.
     *
     * - Returns an empty array when no cell matched any known synonym
     *   (caller can use this as the "headerless CSV" signal).
     * - First-match-wins per leadField: if "phone" appears twice in the
     *   header row, only the first column gets mapped to `phone1`. This
     *   prevents a later catch-all "phone" header from clobbering an
     *   already-mapped `phone1` column.
     *
     * @param  array<int, string>  $headerCells
     * @return array<int, string>  columnIndex => leadField
     */
    public static function buildHeaderMap(array $headerCells): array
    {
        // Flatten synonyms into a single [synonym => leadField] lookup.
        $synonymToField = [];
        foreach (self::FIELD_SYNONYMS as $field => $synonyms) {
            foreach ($synonyms as $syn) {
                $synonymToField[$syn] = $field;
            }
        }

        $map = [];
        foreach ($headerCells as $idx => $cell) {
            $normalized = self::normalize((string) $cell);
            if ($normalized === '') {
                continue;
            }
            if (isset($synonymToField[$normalized])) {
                $field = $synonymToField[$normalized];
                if (!in_array($field, $map, true)) {
                    $map[$idx] = $field;
                }
            }
        }
        return $map;
    }
}
