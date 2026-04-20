<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            if (! Schema::hasColumn('deals', 'week_key')) {
                $table->string('week_key')->nullable()->index();
            }
            if (! Schema::hasColumn('deals', 'week_start_date')) {
                $table->date('week_start_date')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            if (Schema::hasColumn('deals', 'week_key')) {
                $table->dropIndex(['week_key']);
                $table->dropColumn('week_key');
            }
            if (Schema::hasColumn('deals', 'week_start_date')) {
                $table->dropIndex(['week_start_date']);
                $table->dropColumn('week_start_date');
            }
        });
    }
};
