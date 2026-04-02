<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            if (!Schema::hasColumn('deals', 'closing_date')) {
                $table->date('closing_date')->nullable()->after('charged_date');
            }
            if (!Schema::hasColumn('deals', 'disposition_status')) {
                $table->string('disposition_status', 30)->nullable()->after('status');
            }
            if (!Schema::hasColumn('deals', 'callback_date')) {
                $table->dateTime('callback_date')->nullable()->after('disposition_status');
            }
            if (!Schema::hasColumn('deals', 'last_edited_by')) {
                $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete()->after('assigned_admin');
            }
            if (!Schema::hasColumn('deals', 'last_edited_at')) {
                $table->timestamp('last_edited_at')->nullable()->after('last_edited_by');
            }
            if (!Schema::hasColumn('deals', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('charged_back');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['closing_date', 'disposition_status', 'callback_date', 'last_edited_by', 'last_edited_at', 'is_locked']);
        });
    }
};
