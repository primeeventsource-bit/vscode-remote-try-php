<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── AI Trainer coaching events log ──────────────
        if (!Schema::hasTable('ai_trainer_events')) {
            Schema::create('ai_trainer_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users');
                $table->string('role', 30)->nullable();
                $table->string('module', 50);           // leads, deals, clients, settings, etc.
                $table->string('entity_type', 50)->nullable(); // lead, deal, user
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('event_type', 50);        // coaching_shown, mistake_detected, recommendation_given, ask_ai
                $table->json('context_json')->nullable(); // page state, record snapshot
                $table->json('ai_response_json')->nullable();
                $table->string('severity', 20)->nullable(); // info, warning, critical
                $table->timestamps();

                $table->index(['user_id', 'module', 'created_at']);
                $table->index(['entity_type', 'entity_id']);
                $table->index('event_type');
            });
        }

        // ── AI Trainer recommendations ─────────────────
        if (!Schema::hasTable('ai_trainer_recommendations')) {
            Schema::create('ai_trainer_recommendations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users');
                $table->string('module', 50);
                $table->string('entity_type', 50)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('recommendation_type', 50); // next_action, quality_tip, coaching_hint
                $table->string('title');
                $table->text('message');
                $table->string('action_label', 100)->nullable(); // "Add Notes", "Call Lead", etc.
                $table->string('action_target', 255)->nullable(); // route or selector
                $table->string('status', 20)->default('active'); // active, dismissed, completed
                $table->timestamp('dismissed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'module', 'status']);
                $table->index(['entity_type', 'entity_id']);
            });
        }

        // ── AI Trainer detected mistakes ───────────────
        if (!Schema::hasTable('ai_trainer_mistakes')) {
            Schema::create('ai_trainer_mistakes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users');
                $table->string('module', 50);
                $table->string('entity_type', 50)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('mistake_type', 50);      // missing_fields, poor_notes, premature_transfer, skipped_status, etc.
                $table->string('severity', 20);           // low, medium, high
                $table->text('message');
                $table->json('details_json')->nullable();  // which fields missing, etc.
                $table->timestamp('detected_at');
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'module', 'severity']);
                $table->index(['mistake_type', 'detected_at']);
                $table->index(['entity_type', 'entity_id']);
            });
        }

        // ── AI Trainer per-user progress / strengths ───
        if (!Schema::hasTable('ai_trainer_progress')) {
            Schema::create('ai_trainer_progress', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users');
                $table->string('role', 30);
                $table->json('strengths_json')->nullable();
                $table->json('weaknesses_json')->nullable();
                $table->integer('total_hints_shown')->default(0);
                $table->integer('total_mistakes_detected')->default(0);
                $table->integer('total_recommendations_completed')->default(0);
                $table->integer('note_quality_avg')->default(0); // 0-100
                $table->timestamp('last_coached_at')->nullable();
                $table->timestamps();

                $table->unique('user_id');
            });
        }

        // ── AI Sales Scores (lead scoring, deal probability) ──
        if (!Schema::hasTable('ai_sales_scores')) {
            Schema::create('ai_sales_scores', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 30); // lead, deal, user
                $table->unsignedBigInteger('entity_id');
                $table->string('score_type', 50);  // lead_score, close_probability, ghost_risk, followup_priority, agent_quality
                $table->integer('numeric_score');   // 0-100
                $table->string('label', 30)->nullable(); // hot, warm, cold, at_risk
                $table->integer('confidence_score')->nullable(); // 0-100
                $table->json('reasons_json')->nullable();
                $table->json('risks_json')->nullable();
                $table->json('recommendations_json')->nullable();
                $table->timestamp('calculated_at');
                $table->timestamps();

                $table->unique(['entity_type', 'entity_id', 'score_type']);
                $table->index(['score_type', 'numeric_score']);
                $table->index(['entity_type', 'label']);
            });
        }

        // ── Seed default AI Trainer settings ───────────
        self::seedSettings();
    }

    private static function seedSettings(): void
    {
        if (!Schema::hasTable('crm_settings')) return;

        $settings = [
            'ai_trainer.enabled' => true,
            'ai_trainer.fronter_coaching' => true,
            'ai_trainer.closer_coaching' => true,
            'ai_trainer.note_quality_coaching' => true,
            'ai_trainer.mistake_detection' => true,
            'ai_trainer.next_action_hints' => true,
            'ai_trainer.inline_hints' => true,
            'ai_trainer.side_panel' => true,
            'ai_trainer.manager_insights' => true,
            'ai_trainer.lead_scoring' => true,
            'ai_trainer.deal_scoring' => true,
        ];

        foreach ($settings as $key => $value) {
            $exists = DB::table('crm_settings')->where('key', $key)->exists();
            if (!$exists) {
                DB::table('crm_settings')->insert([
                    'key' => $key,
                    'value' => json_encode($value),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_sales_scores');
        Schema::dropIfExists('ai_trainer_progress');
        Schema::dropIfExists('ai_trainer_mistakes');
        Schema::dropIfExists('ai_trainer_recommendations');
        Schema::dropIfExists('ai_trainer_events');
    }
};
