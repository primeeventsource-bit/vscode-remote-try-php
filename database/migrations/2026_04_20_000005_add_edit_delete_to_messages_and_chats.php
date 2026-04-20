<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'edited_at')) {
                $table->timestamp('edited_at')->nullable()->after('seen_at');
            }
            if (!Schema::hasColumn('messages', 'is_deleted')) {
                $table->boolean('is_deleted')->default(false)->after('edited_at');
            }
            if (!Schema::hasColumn('messages', 'original_text')) {
                $table->text('original_text')->nullable()->after('is_deleted');
            }
        });

        Schema::table('chats', function (Blueprint $table) {
            if (!Schema::hasColumn('chats', 'deleted_at')) {
                $table->softDeletes();
            }
            if (!Schema::hasColumn('chats', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            foreach (['edited_at', 'is_deleted', 'original_text'] as $col) {
                if (Schema::hasColumn('messages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('chats', function (Blueprint $table) {
            if (Schema::hasColumn('chats', 'deleted_by')) {
                $table->dropColumn('deleted_by');
            }
            if (Schema::hasColumn('chats', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
