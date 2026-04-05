<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'avatar_emoji')) {
                $table->string('avatar_emoji', 10)->nullable()->after('avatar_path');
            }
        });

        Schema::table('chats', function (Blueprint $table) {
            if (!Schema::hasColumn('chats', 'icon_emoji')) {
                $table->string('icon_emoji', 10)->nullable()->after('icon_path');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'avatar_emoji')) {
            Schema::table('users', function (Blueprint $table) { $table->dropColumn('avatar_emoji'); });
        }
        if (Schema::hasColumn('chats', 'icon_emoji')) {
            Schema::table('chats', function (Blueprint $table) { $table->dropColumn('icon_emoji'); });
        }
    }
};
