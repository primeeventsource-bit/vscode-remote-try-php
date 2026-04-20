<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop CVV columns entirely from the deals table.
 *
 * Migration 2026_04_04_000003_secure_card_data_on_deals NULLed cv2/cv2_2
 * values but left the columns in place. Even with the values destroyed,
 * keeping CVV columns is a PCI DSS gap — any raw DB write path could
 * still populate them. This migration removes the columns so they can
 * never be written to again.
 *
 * Safe to run repeatedly — guarded with Schema::hasColumn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            if (Schema::hasColumn('deals', 'cv2')) {
                $table->dropColumn('cv2');
            }
            if (Schema::hasColumn('deals', 'cv2_2')) {
                $table->dropColumn('cv2_2');
            }
        });
    }

    public function down(): void
    {
        // Intentionally irreversible — CVV storage must never come back.
    }
};
