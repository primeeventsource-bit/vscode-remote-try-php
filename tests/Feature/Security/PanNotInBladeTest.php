<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Static regression check: the admin "Card Information" panel in the Deals
 * Livewire view must never reference $active->card_number / card_number2 / cv2
 * directly. SafeEncrypted decrypts on attribute read, so any direct {{ }} echo
 * dumps the full PAN into the rendered HTML — a PCI-DSS violation.
 *
 * Use the Deal::masked_card / masked_card2 accessors instead.
 */
class PanNotInBladeTest extends TestCase
{
    public function test_deals_blade_does_not_reference_unmasked_card_number(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/deals.blade.php'));
        $this->assertNotEmpty($blade, 'deals.blade.php should exist and be readable.');

        $this->assertStringNotContainsString('$active->card_number', $blade,
            'PCI regression: deals.blade.php must use $active->masked_card, not $active->card_number.');
        $this->assertStringNotContainsString('$active->card_number2', $blade,
            'PCI regression: deals.blade.php must use $active->masked_card2, not $active->card_number2.');
        $this->assertStringNotContainsString('$active->cv2', $blade,
            'CVV regression: cv2/cv2_2 columns are dropped and must never be referenced.');
    }
}
