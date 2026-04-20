<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_rep_stats', function (Blueprint $table) {
            $table->id();
            $table->string('week_key')->index();
            $table->date('week_start');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('role');
            $table->string('office')->nullable();

            // Closer metrics
            $table->integer('deals_total')->default(0);
            $table->integer('deals_closed')->default(0);
            $table->integer('deals_cancelled')->default(0);
            $table->integer('deals_pending')->default(0);
            $table->decimal('revenue_total', 14, 2)->default(0);
            $table->decimal('commission_total', 14, 2)->default(0);
            $table->decimal('avg_sale_amount', 12, 2)->default(0);

            // Fronter metrics
            $table->integer('sets_total')->default(0);
            $table->integer('sets_closed')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);

            // Rankings
            $table->integer('rank_revenue')->nullable();
            $table->integer('rank_deals')->nullable();

            $table->json('daily_stats')->nullable();
            $table->timestamps();

            $table->unique(['week_key', 'user_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_rep_stats');
    }
};
