<?php

namespace Tests\Unit\Casts;

use App\Casts\SafeEncrypted;
use App\Models\Deal;
use Tests\TestCase;

/**
 * SafeEncrypted is the cast that protects card_number / card_number2 at rest.
 * It must (a) round-trip cleanly, (b) tolerate pre-migration plaintext without
 * crashing, (c) treat null transparently, and (d) never leak the cleartext into
 * the encrypted ciphertext.
 */
class SafeEncryptedTest extends TestCase
{
    private function cast(): SafeEncrypted
    {
        return new SafeEncrypted();
    }

    public function test_round_trip_returns_original_value(): void
    {
        $cast = $this->cast();
        $model = new Deal();
        $pan = '4111111111111111';

        $stored = $cast->set($model, 'card_number', $pan, []);
        $this->assertNotSame($pan, $stored, 'Encrypted value must differ from plaintext.');

        $loaded = $cast->get($model, 'card_number', $stored, []);
        $this->assertSame($pan, $loaded);
    }

    public function test_null_passes_through_both_directions(): void
    {
        $cast = $this->cast();
        $model = new Deal();
        $this->assertNull($cast->set($model, 'card_number', null, []));
        $this->assertNull($cast->get($model, 'card_number', null, []));
    }

    public function test_plaintext_pre_migration_value_is_returned_as_is(): void
    {
        // Before secure_card_data_on_deals migration ran, card_number was plaintext.
        // SafeEncrypted::get must not crash on un-decryptable values; it should pass them through.
        $cast = $this->cast();
        $model = new Deal();

        $loaded = $cast->get($model, 'card_number', '4111111111111111', []);
        $this->assertSame('4111111111111111', $loaded);
    }

    public function test_ciphertext_does_not_leak_plaintext_substring(): void
    {
        $cast = $this->cast();
        $model = new Deal();
        $pan = '4111111111111111';

        $cipher = $cast->set($model, 'card_number', $pan, []);
        $this->assertIsString($cipher);
        $this->assertStringNotContainsString($pan, $cipher,
            'Ciphertext must not contain the plaintext PAN as a substring.');
    }
}
