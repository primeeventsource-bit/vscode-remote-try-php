<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('message_type', 20)->default('text')->after('sender_id');
            $table->text('gif_url')->nullable()->after('file_name');
            $table->text('gif_preview_url')->nullable()->after('gif_url');
            $table->string('gif_provider', 30)->nullable()->after('gif_preview_url');
            $table->string('gif_external_id', 120)->nullable()->after('gif_provider');
            $table->string('gif_title')->nullable()->after('gif_external_id');
            $table->json('metadata')->nullable()->after('gif_title');

            $table->index(['chat_id', 'message_type']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['chat_id', 'message_type']);
            $table->dropColumn([
                'message_type',
                'gif_url',
                'gif_preview_url',
                'gif_provider',
                'gif_external_id',
                'gif_title',
                'metadata',
            ]);
        });
    }
};
