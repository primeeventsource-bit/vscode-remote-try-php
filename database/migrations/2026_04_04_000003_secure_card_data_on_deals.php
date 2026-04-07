<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Secures payment card data on the deals table:
 * 1. Adds card_last4 / card_last4_2 columns for display
 * 2. Adds card_brand / card_brand2 columns (derived from card_type)
 * 3. Migrates existing raw card numbers to last-4 only
 * 4. Encrypts full card numbers in-place using Laravel's encrypter
 * 5. Permanently destroys CVV (cv2 / cv2_2) values
 * 6. Adds updated_by tracking column
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add new safe-display columns
        Schema::table('deals', function (Blueprint $table) {
            $table->string('card_last4', 4)->nullable()->after('card_number');
            $table->string('card_brand', 30)->nullable()->after('card_last4');
            $table->string('card_last4_2', 4)->nullable()->after('card_number2');
            $table->string('card_brand2', 30)->nullable()->after('card_last4_2');
            $table->foreignId('updated_by')->nullable()->after('last_edited_by')->constrained('users')->nullOnDelete();
        });

        // Step 2: Extract last4 from existing card numbers, store brand, encrypt full numbers, destroy CVV
        $deals = DB::table('deals')
            ->whereNotNull('card_number')
            ->orWhereNotNull('cv2')
            ->orWhereNotNull('card_number2')
            ->orWhereNotNull('cv2_2')
            ->get(['id', 'card_number', 'card_type', 'card_number2', 'cv2', 'cv2_2']);

        foreach ($deals as $deal) {
            $update = [];

            // Primary card
            if ($deal->card_number && strlen(preg_replace('/\D/', '', $deal->card_number)) >= 4) {
                $digits = preg_replace('/\D/', '', $deal->card_number);
                $update['card_last4'] = substr($digits, -4);
                $update['card_brand'] = $deal->card_type;
                // Encrypt the full card number
                $update['card_number'] = encrypt($deal->card_number);
            }

            // Secondary card
            if ($deal->card_number2 && strlen(preg_replace('/\D/', '', $deal->card_number2)) >= 4) {
                $digits2 = preg_replace('/\D/', '', $deal->card_number2);
                $update['card_last4_2'] = substr($digits2, -4);
                $update['card_brand2'] = $deal->card_type; // fallback
                $update['card_number2'] = encrypt($deal->card_number2);
            }

            // Permanently destroy CVV - NEVER store or keep CVV
            $update['cv2'] = null;
            $update['cv2_2'] = null;

            if (!empty($update)) {
                DB::table('deals')->where('id', $deal->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        // NOTE: CVV data is permanently destroyed and cannot be recovered.
        // Card numbers can be decrypted if APP_KEY hasn't changed.
        $deals = DB::table('deals')->whereNotNull('card_number')->get(['id', 'card_number', 'card_number2']);

        foreach ($deals as $deal) {
            $update = [];
            try {
                if ($deal->card_number) {
                    $update['card_number'] = decrypt($deal->card_number);
                }
                if ($deal->card_number2) {
                    $update['card_number2'] = decrypt($deal->card_number2);
                }
            } catch (\Throwable $e) {
                // If decryption fails, the data was already plain text
            }
            if (!empty($update)) {
                DB::table('deals')->where('id', $deal->id)->update($update);
            }
        }

        Schema::table('deals', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['card_last4', 'card_brand', 'card_last4_2', 'card_brand2', 'updated_by']);
        });
    }
};
