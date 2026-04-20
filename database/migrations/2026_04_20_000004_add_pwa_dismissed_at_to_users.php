<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'pwa_dismissed_at')) {
                $table->timestamp('pwa_dismissed_at')->nullable()->after('idle_since_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'pwa_dismissed_at')) {
                $table->dropColumn('pwa_dismissed_at');
            }
        });
    }
};
