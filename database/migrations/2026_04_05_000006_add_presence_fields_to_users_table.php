<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'presence_status')) {
                $table->string('presence_status', 20)->default('offline')->after('status');
            }
            if (!Schema::hasColumn('users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('presence_status');
            }
            if (!Schema::hasColumn('users', 'last_active_at')) {
                $table->timestamp('last_active_at')->nullable()->after('last_seen_at');
            }
            if (!Schema::hasColumn('users', 'idle_since_at')) {
                $table->timestamp('idle_since_at')->nullable()->after('last_active_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = ['presence_status', 'last_seen_at', 'last_active_at', 'idle_since_at'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('users', $c));
            if (!empty($existing)) $table->dropColumn($existing);
        });
    }
};
