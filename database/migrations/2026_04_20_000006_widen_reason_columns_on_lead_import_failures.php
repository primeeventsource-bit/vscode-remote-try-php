<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lead_import_failures', function (Blueprint $table) {
            $table->text('reason')->change();
            $table->text('duplicate_reason')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('lead_import_failures', function (Blueprint $table) {
            $table->string('reason')->change();
            $table->string('duplicate_reason')->nullable()->change();
        });
    }
};
