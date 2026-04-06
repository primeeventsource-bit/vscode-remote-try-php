<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Performance indexes for pagination + filtering at scale
            try { $table->index('created_at'); } catch (\Throwable $e) {}
            try { $table->index('disposition'); } catch (\Throwable $e) {}
            try { $table->index('source'); } catch (\Throwable $e) {}
            try { $table->index(['assigned_to', 'created_at']); } catch (\Throwable $e) {}
            try { $table->index(['disposition', 'created_at']); } catch (\Throwable $e) {}
        });
    }

    public function down(): void
    {
        // Indexes are safe to leave
    }
};
