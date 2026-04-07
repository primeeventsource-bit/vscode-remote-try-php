<?php

namespace App\Services\AI;

use App\Models\AiSalesScore;
use App\Models\Deal;

/**
 * Rule-based deal close probability engine.
 * Scores 0-100 and classifies as Strong/Medium/Weak/At Risk.
 */
class DealScoringService
{
    public static function score(Deal $deal): array
    {
        $score = 0;
        $reasons = [];
        $risks = [];

        // ── Deal completeness (0-30) ───────────────────
        $fields = 0;
        if (!empty($deal->owner_name)) $fields++;
        if (!empty($deal->primary_phone)) $fields++;
        if (!empty($deal->resort_name)) $fields++;
        if (!empty($deal->email)) $fields++;
        if (!empty($deal->mailing_address)) $fields++;
        if (!empty($deal->fee) && (float) $deal->fee > 0) { $fields++; $reasons[] = 'Fee set: $' . number_format((float) $deal->fee); }
        else $risks[] = 'No fee amount';

        $completenessScore = min(30, $fields * 5);
        $score += $completenessScore;
        if ($fields >= 5) $reasons[] = 'Complete deal data';
        if ($fields < 3) $risks[] = 'Deal data incomplete';

        // ── Payment info (0-20) ────────────────────────
        if (!empty($deal->card_number)) { $score += 15; $reasons[] = 'Payment info on file'; }
        else $risks[] = 'No payment info';

        if (!empty($deal->verification_num)) { $score += 5; $reasons[] = 'Verification number present'; }

        // ── Notes quality (0-15) ───────────────────────
        $noteText = $deal->notes ?? '';
        $wordCount = str_word_count($noteText);
        if ($wordCount >= 20) { $score += 15; $reasons[] = 'Strong deal notes'; }
        elseif ($wordCount >= 8) { $score += 8; }
        else { $risks[] = 'Weak or missing notes'; }

        // ── Status progression (0-25) ──────────────────
        $statusScore = match ($deal->status ?? 'pending') {
            'charged' => 25,
            'verified' => 20,
            'sent_to_verification' => 15,
            'pending' => 5,
            default => 5,
        };
        $score += $statusScore;

        if ($deal->status === 'charged') { $reasons[] = 'Deal is charged — closed'; }
        elseif ($deal->status === 'verified') { $reasons[] = 'Verified by admin'; }
        elseif ($deal->status === 'sent_to_verification') { $reasons[] = 'Sent for verification'; }
        else { $risks[] = 'Still pending'; }

        // ── Chargeback risk (penalty) ──────────────────
        if ($deal->charged_back ?? false) {
            $score = max(5, $score - 30);
            $risks[] = 'CHARGED BACK — revenue at risk';
        }

        // ── Deal value bonus (0-10) ────────────────────
        $fee = (float) ($deal->fee ?? 0);
        if ($fee >= 5000) { $score += 10; $reasons[] = 'High-value deal ($' . number_format($fee) . ')'; }
        elseif ($fee >= 2000) { $score += 5; }

        $score = min(100, max(0, $score));

        $label = match (true) {
            $score >= 70 => 'strong',
            $score >= 45 => 'medium',
            $score >= 20 => 'weak',
            default => 'at_risk',
        };

        $nextAction = match (true) {
            ($deal->charged_back ?? false) => 'Handle chargeback — upload evidence',
            $deal->status === 'charged' => 'Monitor for chargeback risk',
            $deal->status === 'verified' || $deal->status === 'sent_to_verification' => 'Wait for admin to charge',
            empty($deal->card_number) => 'Collect payment information',
            count(array_filter([$deal->owner_name, $deal->primary_phone, $deal->resort_name], fn($f) => empty($f))) > 0 => 'Complete missing deal fields',
            $wordCount < 10 => 'Add detailed deal notes',
            $deal->status === 'pending' => 'Send deal to verification',
            default => 'Review deal and follow up with client',
        };

        $result = [
            'score' => $score,
            'label' => $label,
            'confidence' => min(95, 40 + ($completenessScore * 2)),
            'reasons' => $reasons,
            'risks' => $risks,
            'next_action' => $nextAction,
        ];

        AiSalesScore::upsertScore('deal', $deal->id, 'close_probability', [
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
