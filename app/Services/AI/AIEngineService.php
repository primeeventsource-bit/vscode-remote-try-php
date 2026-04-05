<?php

namespace App\Services\AI;

use App\Models\AiInteraction;
use App\Models\AiPromptTemplate;
use App\Models\Objection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Core AI engine. Uses hybrid approach:
 * 1. Fast local keyword/DB matching first
 * 2. OpenAI API fallback for complex detection/generation
 * 3. All outputs logged to ai_interactions
 * 4. Settings-driven: can be disabled entirely
 */
class AIEngineService
{
    private static function ready(): bool
    {
        return Schema::hasTable('ai_interactions');
    }

    private static function getSetting(string $key, mixed $default): mixed
    {
        try {
            $raw = DB::table('crm_settings')->where('key', $key)->value('value');
            if ($raw !== null) return json_decode($raw, true) ?? $default;
        } catch (\Throwable $e) {}
        return $default;
    }

    private static function isEnabled(): bool
    {
        return (bool) self::getSetting('ai.enable_ai_engine', false);
    }

    private static function getApiKey(): ?string
    {
        return config('services.openai.key') ?: env('OPENAI_API_KEY');
    }

    // ══════════════════════════════════════════════════════════════
    // OBJECTION DETECTION (Hybrid: local first, AI fallback)
    // ══════════════════════════════════════════════════════════════

    public static function detectObjection(User $user, string $text, string $stage = 'closer', ?int $leadId = null, ?int $dealId = null): array
    {
        $startTime = microtime(true);

        // Step 1: Fast local keyword matching
        $localMatches = Objection::detectFromText($text);

        if ($localMatches->isNotEmpty()) {
            $best = $localMatches->first();
            $result = [
                'source' => 'local',
                'category' => $best->category,
                'label' => $best->objection_text,
                'confidence' => 85,
                'objection_id' => $best->id,
                'keywords' => explode(',', $best->keywords ?? ''),
                'recommended_tone' => 'closer',
                'rebuttals' => [
                    'soft' => $best->rebuttal_level_1,
                    'closer' => $best->rebuttal_level_2,
                    'aggressive' => $best->rebuttal_level_3,
                ],
            ];

            self::logInteraction($user, 'objection_detection', $text, $result, $leadId, $dealId, $startTime, 'local');
            return $result;
        }

        // Step 2: AI fallback if enabled
        if (!self::isEnabled() || !self::getApiKey()) {
            return ['source' => 'none', 'category' => null, 'label' => 'No objection detected', 'confidence' => 0];
        }

        $template = AiPromptTemplate::forCategory('objection_detection');
        if (!$template) return ['source' => 'none', 'category' => null, 'confidence' => 0];

        $aiResult = self::callOpenAI(
            $template->system_prompt,
            $template->renderUserPrompt(['input' => self::redact($text), 'stage' => $stage]),
            $user, 'objection_detection', $text, $template->id, $leadId, $dealId, $startTime
        );

        return $aiResult ?: ['source' => 'ai_failed', 'category' => null, 'confidence' => 0];
    }

    // ══════════════════════════════════════════════════════════════
    // NEXT LINE SUGGESTION
    // ══════════════════════════════════════════════════════════════

    public static function suggestNextLine(User $user, string $stage, string $tone, ?string $objection = null, ?string $context = null): array
    {
        $startTime = microtime(true);

        if (!self::isEnabled() || !self::getApiKey()) {
            return ['source' => 'disabled', 'line' => null];
        }

        $template = AiPromptTemplate::forCategory('next_line');
        if (!$template) return ['source' => 'no_template', 'line' => null];

        $result = self::callOpenAI(
            $template->system_prompt,
            $template->renderUserPrompt([
                'stage' => $stage,
                'tone' => $tone,
                'objection' => self::redact($objection ?? 'none'),
                'context' => self::redact($context ?? 'beginning of call'),
            ]),
            $user, 'next_line', $objection ?? '', $template->id, null, null, $startTime
        );

        return $result ?: ['source' => 'ai_failed', 'line' => null];
    }

    // ══════════════════════════════════════════════════════════════
    // REBUTTAL REWRITE
    // ══════════════════════════════════════════════════════════════

    public static function rewriteRebuttal(User $user, string $rebuttal, string $objection, string $tone = 'soft'): array
    {
        $startTime = microtime(true);

        if (!self::isEnabled() || !self::getApiKey()) {
            return ['source' => 'disabled', 'rewritten' => null];
        }

        $template = AiPromptTemplate::forCategory('rebuttal_rewrite', $tone);
        if (!$template) return ['source' => 'no_template', 'rewritten' => null];

        $result = self::callOpenAI(
            $template->system_prompt,
            $template->renderUserPrompt([
                'rebuttal' => self::redact($rebuttal),
                'objection' => self::redact($objection),
            ]),
            $user, 'rebuttal_rewrite', $rebuttal, $template->id, null, null, $startTime
        );

        return $result ?: ['source' => 'ai_failed', 'rewritten' => null];
    }

    // ══════════════════════════════════════════════════════════════
    // FOLLOW-UP QUESTIONS
    // ══════════════════════════════════════════════════════════════

    public static function suggestFollowUp(User $user, string $objection, string $stage = 'closer', string $tone = 'closer'): array
    {
        $startTime = microtime(true);

        if (!self::isEnabled() || !self::getApiKey()) {
            return ['source' => 'disabled', 'questions' => []];
        }

        $template = AiPromptTemplate::forCategory('follow_up');
        if (!$template) return ['source' => 'no_template', 'questions' => []];

        $result = self::callOpenAI(
            $template->system_prompt,
            $template->renderUserPrompt([
                'objection' => self::redact($objection),
                'stage' => $stage,
                'tone' => $tone,
            ]),
            $user, 'follow_up', $objection, $template->id, null, null, $startTime
        );

        return $result ?: ['source' => 'ai_failed', 'questions' => []];
    }

    // ══════════════════════════════════════════════════════════════
    // OPENAI API CALL
    // ══════════════════════════════════════════════════════════════

    private static function callOpenAI(
        string $systemPrompt, string $userPrompt,
        User $user, string $type, string $inputText,
        ?int $templateId, ?int $leadId, ?int $dealId,
        float $startTime
    ): ?array {
        try {
            $model = self::getSetting('ai.ai_model', 'gpt-4o-mini');
            $maxTokens = (int) self::getSetting('ai.ai_max_tokens_default', 500);
            $temperature = (float) self::getSetting('ai.ai_temperature_default', 0.7);
            $timeout = (int) self::getSetting('ai.ai_timeout_seconds', 15);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . self::getApiKey(),
                'Content-Type' => 'application/json',
            ])->timeout($timeout)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
            ]);

            $elapsed = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->failed()) {
                self::logInteraction($user, $type, $inputText, null, $leadId, $dealId, $startTime, $model, $templateId, 'failed', $response->body());
                return null;
            }

            $body = $response->json();
            $outputText = $body['choices'][0]['message']['content'] ?? '';

            // Try to parse as JSON
            $outputJson = null;
            $cleaned = trim($outputText);
            if (str_starts_with($cleaned, '{') || str_starts_with($cleaned, '[')) {
                $outputJson = json_decode($cleaned, true);
            }

            $result = $outputJson ?? ['text' => $outputText];
            $result['source'] = 'ai';
            $result['model'] = $model;

            self::logInteraction($user, $type, $inputText, $result, $leadId, $dealId, $startTime, $model, $templateId, 'success');

            return $result;
        } catch (\Throwable $e) {
            Log::error('AI engine error', ['error' => $e->getMessage(), 'type' => $type]);
            self::logInteraction($user, $type, $inputText, null, $leadId, $dealId, $startTime, null, $templateId, 'failed', $e->getMessage());
            return null;
        }
    }

    // ══════════════════════════════════════════════════════════════
    // LOGGING
    // ══════════════════════════════════════════════════════════════

    private static function logInteraction(
        User $user, string $type, string $input, ?array $output,
        ?int $leadId, ?int $dealId, float $startTime,
        ?string $model = null, ?int $templateId = null,
        string $status = 'success', ?string $error = null
    ): void {
        if (!self::ready()) return;

        try {
            AiInteraction::create([
                'user_id' => $user->id,
                'lead_id' => $leadId,
                'deal_id' => $dealId,
                'type' => $type,
                'input_text' => substr($input, 0, 5000),
                'output_text' => $output ? substr(json_encode($output), 0, 10000) : null,
                'output_json' => $output,
                'model_used' => $model,
                'prompt_template_id' => $templateId,
                'confidence_score' => $output['confidence'] ?? null,
                'response_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status' => $status,
                'error_message' => $error,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function logFeedback(int $interactionId, User $user, string $type): void
    {
        if (!self::ready()) return;
        try {
            \App\Models\AiFeedback::create([
                'interaction_id' => $interactionId,
                'user_id' => $user->id,
                'feedback_type' => $type,
            ]);
        } catch (\Throwable $e) {}
    }

    // ══════════════════════════════════════════════════════════════
    // REDACTION
    // ══════════════════════════════════════════════════════════════

    private static function redact(string $text): string
    {
        // Remove credit card numbers
        $text = preg_replace('/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/', '[REDACTED_CARD]', $text);
        // Remove CVV
        $text = preg_replace('/\bcvv?\s*[:=]?\s*\d{3,4}\b/i', '[REDACTED_CVV]', $text);
        // Remove SSN-like
        $text = preg_replace('/\b\d{3}-\d{2}-\d{4}\b/', '[REDACTED_SSN]', $text);
        return $text;
    }
}
