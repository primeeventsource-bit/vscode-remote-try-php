<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('category', 30)->index();
            $table->longText('system_prompt');
            $table->longText('user_prompt_template');
            $table->string('tone', 20)->nullable();
            $table->string('stage', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->string('type', 30)->index();
            $table->longText('input_text')->nullable();
            $table->json('context_json')->nullable();
            $table->longText('output_text')->nullable();
            $table->json('output_json')->nullable();
            $table->string('model_used', 50)->nullable();
            $table->foreignId('prompt_template_id')->nullable()->constrained('ai_prompt_templates')->nullOnDelete();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->string('status', 20)->default('success');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'created_at']);
        });

        Schema::create('ai_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interaction_id')->constrained('ai_interactions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('feedback_type', 20); // helpful, not_helpful, used, worked, failed
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        self::seedPrompts();
    }

    private static function seedPrompts(): void
    {
        $prompts = [
            [
                'name' => 'Objection Detection',
                'slug' => 'objection-detection',
                'category' => 'objection_detection',
                'system_prompt' => "You are a sales objection detection AI for a timeshare exit company. Analyze the client's statement and identify the objection category.\n\nCategories: money, timing, spouse, trust, card, thinking, interest, competitor, other\n\nReturn ONLY valid JSON with these fields:\n- category: string\n- label: string (short objection name)\n- confidence: number 0-100\n- keywords: array of matched keywords\n- recommended_tone: string (soft/closer/aggressive)",
                'user_prompt_template' => "Client said: \"{{input}}\"\n\nCurrent stage: {{stage}}\nDetect the objection category and return JSON.",
            ],
            [
                'name' => 'Next Line Suggestion',
                'slug' => 'next-line',
                'category' => 'next_line',
                'system_prompt' => "You are a live sales coaching AI for a timeshare exit company. Generate the next best line for the sales rep to say.\n\nRules:\n- Keep it under 2 sentences\n- Match the tone requested\n- Be natural and conversational\n- Never include payment authorization language\n- Never make guarantees about timelines\n\nReturn ONLY valid JSON:\n- line: string\n- tone: string\n- reason: string (why this line works)",
                'user_prompt_template' => "Stage: {{stage}}\nTone: {{tone}}\nClient objection: \"{{objection}}\"\nContext: {{context}}\n\nGenerate the next best line to say.",
            ],
            [
                'name' => 'Rebuttal Rewrite — Soft',
                'slug' => 'rebuttal-rewrite-soft',
                'category' => 'rebuttal_rewrite',
                'tone' => 'soft',
                'system_prompt' => "You are a sales rebuttal rewriter. Rewrite the given rebuttal in a SOFT, empathetic tone. Keep it under 3 sentences. Be understanding and non-pushy.\n\nReturn ONLY the rewritten text, no JSON.",
                'user_prompt_template' => "Original rebuttal: \"{{rebuttal}}\"\nObjection: \"{{objection}}\"\n\nRewrite in soft, empathetic tone.",
            ],
            [
                'name' => 'Rebuttal Rewrite — Closer',
                'slug' => 'rebuttal-rewrite-closer',
                'category' => 'rebuttal_rewrite',
                'tone' => 'closer',
                'system_prompt' => "You are a sales rebuttal rewriter. Rewrite the given rebuttal in a CLOSER tone — confident, logical, and persuasive with urgency. Keep it under 3 sentences.\n\nReturn ONLY the rewritten text, no JSON.",
                'user_prompt_template' => "Original rebuttal: \"{{rebuttal}}\"\nObjection: \"{{objection}}\"\n\nRewrite in confident closer tone with urgency.",
            ],
            [
                'name' => 'Rebuttal Rewrite — Aggressive',
                'slug' => 'rebuttal-rewrite-aggressive',
                'category' => 'rebuttal_rewrite',
                'tone' => 'aggressive',
                'system_prompt' => "You are a sales rebuttal rewriter. Rewrite the given rebuttal in a DIRECT, challenging tone — assertive but not rude. Keep it under 3 sentences. Challenge the objection head-on.\n\nReturn ONLY the rewritten text, no JSON.",
                'user_prompt_template' => "Original rebuttal: \"{{rebuttal}}\"\nObjection: \"{{objection}}\"\n\nRewrite in direct, challenging tone.",
            ],
            [
                'name' => 'Follow-Up Questions',
                'slug' => 'follow-up-questions',
                'category' => 'follow_up',
                'system_prompt' => "You are a sales coaching AI. Generate 3 follow-up questions the rep should ask to isolate the objection and move toward closing.\n\nRules:\n- Questions should be open-ended\n- Each under 1 sentence\n- Ranked by effectiveness\n\nReturn ONLY valid JSON array of 3 strings.",
                'user_prompt_template' => "Objection: \"{{objection}}\"\nStage: {{stage}}\nTone: {{tone}}\n\nGenerate 3 follow-up questions.",
            ],
        ];

        foreach ($prompts as $p) {
            $p['is_active'] = true;
            $p['version'] = 1;
            $p['created_at'] = now();
            $p['updated_at'] = now();
            DB::table('ai_prompt_templates')->insert($p);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_feedback');
        Schema::dropIfExists('ai_interactions');
        Schema::dropIfExists('ai_prompt_templates');
    }
};
