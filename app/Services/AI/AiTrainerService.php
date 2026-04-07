<?php

namespace App\Services\AI;

use App\Models\AiTrainerEvent;
use App\Models\AiTrainerMistake;
use App\Models\AiTrainerProgress;
use App\Models\AiTrainerRecommendation;
use App\Models\Lead;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Core AI Trainer orchestrator.
 * Routes coaching requests to role-specific services.
 * Manages events, mistakes, recommendations, and progress.
 */
class AiTrainerService
{
    // ── Settings ───────────────────────────────────────
    public static function isEnabled(): bool
    {
        return (bool) self::setting('ai_trainer.enabled', true);
    }

    public static function isModuleEnabled(string $module): bool
    {
        return self::isEnabled() && (bool) self::setting("ai_trainer.{$module}", true);
    }

    private static function setting(string $key, mixed $default = null): mixed
    {
        try {
            $raw = DB::table('crm_settings')->where('key', $key)->value('value');
            if ($raw !== null) return json_decode($raw, true) ?? $default;
        } catch (\Throwable $e) {}
        return $default;
    }

    // ── Coaching Entry Point ───────────────────────────
    public static function coachForLead(User $user, Lead $lead): array
    {
        if (!self::isEnabled()) return self::empty();

        $role = $user->role;
        $coaching = match (true) {
            in_array($role, ['fronter', 'fronter_panama']) => FronterCoachService::coachLead($user, $lead),
            in_array($role, ['closer', 'closer_panama'])   => CloserCoachService::coachLead($user, $lead),
            default => FronterCoachService::coachLead($user, $lead), // admins see fronter view
        };

        // Log coaching event
        self::logEvent($user, 'leads', 'lead', $lead->id, 'coaching_shown', [
            'lead_status' => $lead->disposition,
            'lead_name' => $lead->owner_name,
        ], $coaching);

        return $coaching;
    }

    public static function coachForDeal(User $user, Deal $deal): array
    {
        if (!self::isEnabled()) return self::empty();

        $coaching = CloserCoachService::coachDeal($user, $deal);

        self::logEvent($user, 'deals', 'deal', $deal->id, 'coaching_shown', [
            'deal_status' => $deal->status ?? null,
            'deal_fee' => $deal->fee ?? null,
        ], $coaching);

        return $coaching;
    }

    // ── Next Best Action ──────────────────────────────
    public static function nextActionForLead(User $user, Lead $lead): array
    {
        if (!self::isEnabled()) return ['action' => null, 'reason' => null];

        $role = $user->role;
        return match (true) {
            in_array($role, ['fronter', 'fronter_panama']) => FronterCoachService::nextAction($lead),
            in_array($role, ['closer', 'closer_panama'])   => CloserCoachService::nextActionForLead($lead),
            default => FronterCoachService::nextAction($lead),
        };
    }

    // ── Mistake Detection ─────────────────────────────
    public static function detectLeadMistakes(User $user, Lead $lead): array
    {
        if (!self::isModuleEnabled('mistake_detection')) return [];
        return MistakeDetectionService::detectForLead($user, $lead);
    }

    public static function detectDealMistakes(User $user, Deal $deal): array
    {
        if (!self::isModuleEnabled('mistake_detection')) return [];
        return MistakeDetectionService::detectForDeal($user, $deal);
    }

    // ── Note Quality ──────────────────────────────────
    public static function scoreNote(string $noteText): array
    {
        if (!self::isModuleEnabled('note_quality_coaching')) return ['score' => 0, 'feedback' => []];
        return NoteQualityService::score($noteText);
    }

    // ── Lead Scoring ──────────────────────────────────
    public static function scoreLead(Lead $lead): array
    {
        if (!self::isModuleEnabled('lead_scoring')) return ['score' => 0, 'label' => 'unknown'];
        return LeadScoringService::score($lead);
    }

    // ── Deal Close Probability ────────────────────────
    public static function scoreDeal(Deal $deal): array
    {
        if (!self::isModuleEnabled('deal_scoring')) return ['score' => 0, 'label' => 'unknown'];
        return DealScoringService::score($deal);
    }

    // ── Full Coaching Package (one call, all data) ────
    public static function getFullCoaching(User $user, string $module, ?int $entityId = null): array
    {
        if (!self::isEnabled()) return self::empty();

        $result = self::empty();

        if ($module === 'leads' && $entityId) {
            $lead = Lead::find($entityId);
            if ($lead) {
                $result['coaching'] = self::coachForLead($user, $lead);
                $result['next_action'] = self::nextActionForLead($user, $lead);
                $result['mistakes'] = self::detectLeadMistakes($user, $lead);
                $result['lead_score'] = self::scoreLead($lead);
                $result['note_quality'] = null; // scored on demand per note
            }
        } elseif ($module === 'deals' && $entityId) {
            $deal = Deal::find($entityId);
            if ($deal) {
                $result['coaching'] = self::coachForDeal($user, $deal);
                $result['mistakes'] = self::detectDealMistakes($user, $deal);
                $result['deal_score'] = self::scoreDeal($deal);
            }
        }

        // Active recommendations for this user
        $result['recommendations'] = AiTrainerRecommendation::where('user_id', $user->id)
            ->where('module', $module)
            ->active()
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->toArray();

        // Recent mistakes
        $result['recent_mistakes'] = AiTrainerMistake::where('user_id', $user->id)
            ->unresolved()
            ->orderByDesc('detected_at')
            ->limit(5)
            ->get()
            ->toArray();

        return $result;
    }

    // ── Manager Insights ──────────────────────────────
    public static function getManagerInsights(): array
    {
        if (!Schema::hasTable('ai_trainer_mistakes')) return [];

        $topMistakes = AiTrainerMistake::select('mistake_type')
            ->selectRaw('COUNT(*) as cnt')
            ->where('detected_at', '>=', now()->subDays(30))
            ->groupBy('mistake_type')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        $weakUsers = AiTrainerMistake::select('user_id')
            ->selectRaw('COUNT(*) as cnt')
            ->where('detected_at', '>=', now()->subDays(30))
            ->whereNull('resolved_at')
            ->groupBy('user_id')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get()
            ->map(fn($row) => [
                'user_id' => $row->user_id,
                'user' => User::find($row->user_id),
                'mistake_count' => $row->cnt,
            ]);

        return [
            'top_mistakes' => $topMistakes,
            'users_needing_coaching' => $weakUsers,
        ];
    }

    // ── Event Logging ─────────────────────────────────
    public static function logEvent(User $user, string $module, ?string $entityType, ?int $entityId, string $eventType, ?array $context, ?array $response, ?string $severity = null): void
    {
        if (!Schema::hasTable('ai_trainer_events')) return;

        try {
            AiTrainerEvent::create([
                'user_id' => $user->id,
                'role' => $user->role,
                'module' => $module,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'event_type' => $eventType,
                'context_json' => $context,
                'ai_response_json' => $response,
                'severity' => $severity,
            ]);
        } catch (\Throwable $e) {}
    }

    // ── Save Mistake ──────────────────────────────────
    public static function saveMistake(User $user, string $module, ?string $entityType, ?int $entityId, string $type, string $severity, string $message, ?array $details = null): void
    {
        if (!Schema::hasTable('ai_trainer_mistakes')) return;

        try {
            AiTrainerMistake::create([
                'user_id' => $user->id,
                'module' => $module,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'mistake_type' => $type,
                'severity' => $severity,
                'message' => $message,
                'details_json' => $details,
                'detected_at' => now(),
            ]);

            // Update progress
            AiTrainerProgress::updateOrCreate(
                ['user_id' => $user->id],
                ['role' => $user->role]
            )->increment('total_mistakes_detected');
        } catch (\Throwable $e) {}
    }

    // ── Save Recommendation ───────────────────────────
    public static function saveRecommendation(User $user, string $module, ?string $entityType, ?int $entityId, string $type, string $title, string $message, ?string $actionLabel = null, ?string $actionTarget = null): void
    {
        if (!Schema::hasTable('ai_trainer_recommendations')) return;

        try {
            AiTrainerRecommendation::create([
                'user_id' => $user->id,
                'module' => $module,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'recommendation_type' => $type,
                'title' => $title,
                'message' => $message,
                'action_label' => $actionLabel,
                'action_target' => $actionTarget,
            ]);
        } catch (\Throwable $e) {}
    }

    // ── Dismiss / Complete ────────────────────────────
    public static function dismissRecommendation(int $id, int $userId): void
    {
        AiTrainerRecommendation::where('id', $id)->where('user_id', $userId)
            ->update(['status' => 'dismissed', 'dismissed_at' => now()]);
    }

    public static function completeRecommendation(int $id, int $userId): void
    {
        AiTrainerRecommendation::where('id', $id)->where('user_id', $userId)
            ->update(['status' => 'completed']);
        AiTrainerProgress::where('user_id', $userId)->increment('total_recommendations_completed');
    }

    public static function resolveMistake(int $id, int $userId): void
    {
        AiTrainerMistake::where('id', $id)->where('user_id', $userId)
            ->update(['resolved_at' => now()]);
    }

    private static function empty(): array
    {
        return [
            'coaching' => null,
            'next_action' => null,
            'mistakes' => [],
            'recommendations' => [],
            'recent_mistakes' => [],
            'lead_score' => null,
            'deal_score' => null,
            'note_quality' => null,
        ];
    }
}
