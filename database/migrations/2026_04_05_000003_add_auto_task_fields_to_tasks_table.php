<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('auto_created')->default(false)->after('notes')->index();
            $table->string('related_type', 50)->nullable()->after('auto_created');
            $table->unsignedBigInteger('related_id')->nullable()->after('related_type');
            $table->text('description')->nullable()->after('title');
            $table->foreignId('completed_by_user_id')->nullable()->after('completed_at')->constrained('users');
            $table->json('metadata')->nullable()->after('completed_by_user_id');

            $table->index(['related_type', 'related_id']);
            $table->index(['assigned_to', 'status']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $cols = ['auto_created', 'related_type', 'related_id', 'description', 'completed_by_user_id', 'metadata'];
            foreach ($cols as $col) {
                if ($col === 'completed_by_user_id') {
                    try { $table->dropForeign(['completed_by_user_id']); } catch (\Throwable $e) {}
                }
            }
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('tasks', $c));
            if (!empty($existing)) $table->dropColumn($existing);
        });
    }
};
