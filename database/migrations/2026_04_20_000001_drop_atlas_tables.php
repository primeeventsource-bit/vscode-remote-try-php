<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the Atlas intelligence tables left behind after commit 339d1cd
 * removed the Atlas UI + code. Production audit showed atlas_leads at
 * 60,844 rows / 18 MB — the biggest table in the DB, with no code
 * reading from or writing to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('atlas_parse_logs');
        Schema::dropIfExists('atlas_leads');
    }

    public function down(): void
    {
        // Tables are gone permanently — no way to restore the data.
    }
};
