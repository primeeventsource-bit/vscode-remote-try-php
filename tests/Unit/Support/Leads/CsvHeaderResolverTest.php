<?php

namespace Tests\Unit\Support\Leads;

use App\Support\Leads\CsvHeaderResolver;
use PHPUnit\Framework\TestCase;

/**
 * Pins the CSV-header → Lead-field resolver lifted out of Leads.php.
 * Pure logic — no Laravel needed, so this is a plain PHPUnit\TestCase.
 */
class CsvHeaderResolverTest extends TestCase
{
    public function test_normalize_lowercases_strips_non_alnum_and_keeps_digits(): void
    {
        $this->assertSame('phonenumber1', CsvHeaderResolver::normalize('Phone Number 1'));
        $this->assertSame('countystate',  CsvHeaderResolver::normalize('County/State'));
        $this->assertSame('owner',        CsvHeaderResolver::normalize('  Owner  '));
        $this->assertSame('email',        CsvHeaderResolver::normalize("E-Mail!"));
        $this->assertSame('',             CsvHeaderResolver::normalize('---'));
    }

    public function test_empty_input_returns_empty_map(): void
    {
        $this->assertSame([], CsvHeaderResolver::buildHeaderMap([]));
    }

    public function test_unknown_headers_yield_empty_map(): void
    {
        $this->assertSame([], CsvHeaderResolver::buildHeaderMap(['xyzzy', 'foo', 'bar']));
    }

    public function test_canonical_header_row_maps_each_column(): void
    {
        $headers = [
            'Resort', 'Owner Name', 'Phone 1', 'Phone 2',
            'City', 'ST', 'Zip', 'Resort Location', 'Email',
        ];
        $expected = [
            0 => 'resort',
            1 => 'owner_name',
            2 => 'phone1',
            3 => 'phone2',
            4 => 'city',
            5 => 'st',
            6 => 'zip',
            7 => 'resort_location',
            8 => 'email',
        ];
        $this->assertSame($expected, CsvHeaderResolver::buildHeaderMap($headers));
    }

    public function test_resolution_is_case_and_punctuation_insensitive(): void
    {
        $headers = ['PHONE NUMBER 1', 'e-mail', 'Z.I.P.', 'Zip-Code'];
        $map     = CsvHeaderResolver::buildHeaderMap($headers);

        $this->assertSame('phone1', $map[0] ?? null);
        $this->assertSame('email',  $map[1] ?? null);
        $this->assertSame('zip',    $map[2] ?? null, 'Z.I.P. with punctuation must normalize to zip.');
        // First-match-wins — `zipcode` resolves to `zip` too but since col 2
        // already claimed `zip`, col 3 must NOT also map to `zip`.
        $this->assertArrayNotHasKey(3, $map,
            'First-match-wins: a second column resolving to an already-mapped field must be skipped.');
    }

    public function test_first_match_wins_on_duplicate_phone_columns(): void
    {
        // "Phone 1" + "Phone" both resolve to phone1; only column 0 should win.
        // (This protects against legacy CSV exports that put a generic "Phone"
        // column AFTER an explicit "Phone 1" column.)
        $headers = ['Phone 1', 'Cell', 'Phone'];
        $map     = CsvHeaderResolver::buildHeaderMap($headers);

        $this->assertSame('phone1', $map[0] ?? null);
        // Cell is in phone1's synonym list too — first-match-wins, so col 1 is skipped.
        $this->assertArrayNotHasKey(1, $map);
        // Phone (the generic synonym for phone1) — also skipped.
        $this->assertArrayNotHasKey(2, $map);
    }

    public function test_owner_name_2_synonyms(): void
    {
        $headers = ['Spouse', 'Co-Owner', 'Joint Owner', 'Owner 2'];
        $map     = CsvHeaderResolver::buildHeaderMap($headers);

        // First column — `spouse` — wins for owner_name_2; the rest are skipped.
        $this->assertSame('owner_name_2', $map[0] ?? null);
        $this->assertArrayNotHasKey(1, $map);
        $this->assertArrayNotHasKey(2, $map);
        $this->assertArrayNotHasKey(3, $map);
    }

    public function test_description_synonyms(): void
    {
        $cases = ['Description', 'Notes', 'Comments', 'Memo', 'Remarks'];
        foreach ($cases as $cell) {
            $map = CsvHeaderResolver::buildHeaderMap([$cell]);
            $this->assertSame('description', $map[0] ?? null,
                "Cell {$cell} should resolve to description.");
        }
    }

    public function test_county_state_combined_field(): void
    {
        $map = CsvHeaderResolver::buildHeaderMap(['County/State']);
        $this->assertSame('countystate', $map[0] ?? null);
    }

    public function test_blank_header_cells_are_skipped(): void
    {
        $headers = ['', 'Owner', '   ', 'Phone'];
        $map     = CsvHeaderResolver::buildHeaderMap($headers);
        $this->assertArrayNotHasKey(0, $map);
        $this->assertSame('owner_name', $map[1] ?? null);
        $this->assertArrayNotHasKey(2, $map);
        $this->assertSame('phone1', $map[3] ?? null);
    }
}
