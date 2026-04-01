<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'avatar_path')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('avatar_path')->nullable()->after('avatar');
            });
        }

        if (!Schema::hasColumn('chats', 'icon_path')) {
            Schema::table('chats', function (Blueprint $table) {
                $table->string('icon_path')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->dropColumn('icon_path');
        });
    }
};
