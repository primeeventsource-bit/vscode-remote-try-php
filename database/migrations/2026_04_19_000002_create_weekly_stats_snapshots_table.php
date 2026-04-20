<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_stats_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('week_key')->unique();
            $table->date('week_start')->index();
            $table->date('week_end');

            $table->integer('total_deals')->default(0);
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->decimal('total_commissions', 14, 2)->default(0);
            $table->integer('closed_deals')->default(0);
            $table->integer('pending_deals')->default(0);
            $table->integer('cancelled_deals')->default(0);
            $table->integer('chargeback_deals')->default(0);
            $table->decimal('chargeback_revenue', 14, 2)->default(0);

            $table->integer('unique_closers')->default(0);
            $table->integer('unique_fronters')->default(0);

            $table->json('closer_breakdown')->nullable();
            $table->json('fronter_breakdown')->nullable();
            $table->json('daily_breakdown')->nullable();
            $table->json('hourly_breakdown')->nullable();
            $table->json('comparison_vs_prev')->nullable();
            $table->json('raw_metrics')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_stats_snapshots');
    }
};
