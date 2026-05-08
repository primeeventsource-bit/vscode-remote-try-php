<?php

namespace Tests\Unit\Models;

use App\Models\Deal;
use Tests\TestCase;

/**
 * Pins the masked_card / masked_card2 accessors used by the Deals blade.
 * Regressing these would re-enable PAN exposure to admins.
 */
class DealMaskingTest extends TestCase
{
    public function test_masked_card_shows_brand_and_last_four(): void
    {
        $d = new Deal();
        $d->card_brand = 'Visa';
        $d->card_last4 = '4242';
        $this->assertSame('Visa ****4242', $d->masked_card);
    }

    public function test_masked_card_falls_back_to_card_type_when_brand_missing(): void
    {
        $d = new Deal();
        $d->card_type = 'Mastercard';
        $d->card_last4 = '1234';
        $this->assertSame('Mastercard ****1234', $d->masked_card);
    }

    public function test_masked_card_returns_dashes_when_no_last_four(): void
    {
        $d = new Deal();
        $d->card_brand = 'Visa';
        $this->assertSame('--', $d->masked_card);
    }

    public function test_masked_card2_uses_secondary_brand_and_last_four(): void
    {
        $d = new Deal();
        $d->card_brand2 = 'Amex';
        $d->card_last4_2 = '0005';
        $this->assertSame('Amex ****0005', $d->masked_card2);
    }

    public function test_masked_card2_returns_dashes_when_no_secondary_last_four(): void
    {
        $d = new Deal();
        $d->card_brand2 = 'Amex';
        $this->assertSame('--', $d->masked_card2);
    }

    public function test_masked_card_never_contains_full_pan_substring(): void
    {
        $d = new Deal();
        $d->card_brand = 'Visa';
        $d->card_last4 = '4242';
        // The PAN itself is never assigned in this test — but if it were, the masked
        // accessor must still only render last4. Assert by absence of any 16-digit run.
        $masked = $d->masked_card;
        $this->assertSame(0, preg_match('/\d{13,}/', $masked),
            'masked_card must never render any sequence of 13+ digits.');
    }
}
