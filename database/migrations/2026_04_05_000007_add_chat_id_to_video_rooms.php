<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('video_rooms', 'chat_id')) {
            Schema::table('video_rooms', function (Blueprint $table) {
                $table->foreignId('chat_id')->nullable()->after('type')->constrained('chats');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('video_rooms', 'chat_id')) {
            Schema::table('video_rooms', function (Blueprint $table) {
                $table->dropForeign(['chat_id']);
                $table->dropColumn('chat_id');
            });
        }
    }
};
