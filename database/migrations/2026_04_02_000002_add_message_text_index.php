<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('messages', function (Blueprint $table) {
                $table->index(['chat_id', 'sender_id']);
            });
        } catch (\Throwable $e) {
            // Index may already exist
        }
    }

    public function down(): void
    {
        try {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropIndex(['chat_id', 'sender_id']);
            });
        } catch (\Throwable $e) {}
    }
};
