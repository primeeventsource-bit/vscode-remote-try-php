<?php

namespace App\Services\AI;

use App\Models\AiSalesScore;
use App\Models\Lead;

/**
 * Rule-based lead scoring engine.
 * Scores 0-100, classifies as Hot/Warm/Cold/At Risk.
 */
class LeadScoringService
{
    public static function score(Lead $lead): array
    {
        $score = 0;
        $reasons = [];
        $risks = [];

        // ── Contact Completeness (0-25) ────────────────
        $contactScore = 0;
        if (!empty($lead->phone1)) $contactScore += 10;
        else $risks[] = 'No phone number';

        if (!empty($lead->phone2)) $contactScore += 3;
        if (!empty($lead->email)) $contactScore += 5;
        else $risks[] = 'No email';

        if (!empty($lead->owner_name)) $contactScore += 5;
        if (!empty($lead->city) || !empty($lead->st)) $contactScore += 2;
        $score += $contactScore;
        if ($contactScore >= 20) $reasons[] = 'Complete contact info';

        // ── Disposition / Engagement (0-30) ────────────
        $dispoScore = match ($lead->disposition) {
            'Right Number' => 25,
            'Callback' => 20,
            'Left Voice Mail' => 10,
            'Transferred to Closer' => 30,
            'Converted to Deal' => 30,
            'Wrong Number', 'Disconnected' => 0,
            default => 5, // undisposed
        };
        $score += $dispoScore;

        if ($lead->disposition === 'Right Number') $reasons[] = 'Owner confirmed';
        if ($lead->disposition === 'Callback') $reasons[] = 'Callback scheduled';
        if ($lead->disposition === 'Transferred to Closer') $reasons[] = 'Already transferred';
        if (in_array($lead->disposition, ['Wrong Number', 'Disconnected'])) $risks[] = 'Dead number';
        if (!$lead->disposition) $risks[] = 'Never contacted';

        // ── Note Quality (0-20) ────────────────────────
        $noteText = $lead->notes ?? '';
        $wordCount = str_word_count($noteText);
        if ($wordCount >= 20) { $score += 20; $reasons[] = 'Detailed notes'; }
        elseif ($wordCount >= 10) { $score += 12; }
        elseif ($wordCount >= 3) { $score += 5; $risks[] = 'Notes are thin'; }
        else { $risks[] = 'No notes'; }

        // ── Freshness (0-15) ───────────────────────────
        $ageDays = $lead->created_at ? $lead->created_at->diffInDays(now()) : 999;
        if ($ageDays <= 1) { $score += 15; $reasons[] = 'New lead (today)'; }
        elseif ($ageDays <= 3) { $score += 12; $reasons[] = 'Fresh lead (<3 days)'; }
        elseif ($ageDays <= 7) { $score += 8; }
        elseif ($ageDays <= 30) { $score += 3; $risks[] = 'Lead aging (' . $ageDays . ' days)'; }
        else { $risks[] = 'Stale lead (' . $ageDays . ' days old)'; }

        // ── Callback urgency bonus (0-10) ──────────────
        if ($lead->disposition === 'Callback' && $lead->callback_date) {
            if ($lead->callback_date->isPast()) {
                $score += 10;
                $reasons[] = 'Callback overdue — act now';
            } elseif ($lead->callback_date->isToday()) {
                $score += 8;
                $reasons[] = 'Callback due today';
            } else {
                $score += 3;
            }
        }

        $score = min(100, max(0, $score));

        $label = match (true) {
            $score >= 70 => 'hot',
            $score >= 45 => 'warm',
            $score >= 20 => 'cold',
            default => 'at_risk',
        };

        // Determine next best action
        $nextAction = match (true) {
            in_array($lead->disposition, ['Wrong Number', 'Disconnected']) => 'Skip — dead number',
            !$lead->disposition => 'Call this lead now',
            $lead->disposition === 'Callback' && ($lead->callback_date?->isPast() ?? false) => 'Call back immediately — overdue',
            $lead->disposition === 'Right Number' && $wordCount < 10 => 'Add detailed notes before transferring',
            $lead->disposition === 'Right Number' && $wordCount >= 10 => 'Transfer to closer',
            $lead->disposition === 'Left Voice Mail' => 'Try calling again',
            default => 'Review lead and decide next step',
        };

        $result = [
            'score' => $score,
            'label' => $label,
            'confidence' => min(95, 50 + ($contactScore * 2)),
            'reasons' => $reasons,
            'risks' => $risks,
            'next_action' => $nextAction,
        ];

        // Persist score
        AiSalesScore::upsertScore('lead', $lead->id, 'lead_score', [
            'numeric_score' => $score,
            'label' => $label,
            'confidence_score' => $result['confidence'],
            'reasons_json' => $reasons,
            'risks_json' => $risks,
            'recommendations_json' => [$nextAction],
        ]);

        return $result;
    }
}
