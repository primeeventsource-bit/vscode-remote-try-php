<?php

namespace App\Services\AI;

use App\Models\Lead;
use App\Models\Deal;
use App\Models\User;

/**
 * Detects workflow mistakes for leads and deals.
 * Saves detected mistakes to the database and returns them for the UI.
 */
class MistakeDetectionService
{
    public static function detectForLead(User $user, Lead $lead): array
    {
        $mistakes = [];

        // 1. Missing required fields
        $missing = [];
        if (empty($lead->owner_name)) $missing[] = 'Owner Name';
        if (empty($lead->phone1)) $missing[] = 'Phone';
        if (empty($lead->resort)) $missing[] = 'Resort';

        if (count($missing) > 0) {
            $m = [
                'mistake_type' => 'missing_fields',
                'severity' => count($missing) >= 2 ? 'high' : 'medium',
                'message' => 'Missing required fields: ' . implode(', ', $missing),
                'details' => ['fields' => $missing],
            ];
            $mistakes[] = $m;
            AiTrainerService::saveMistake($user, 'leads', 'lead', $lead->id, $m['mistake_type'], $m['severity'], $m['message'], $m['details']);
        }

        // 2. Poor notes
        $noteText = $lead->notes ?? '';
        if (strlen(trim($noteText)) > 0 && str_word_count($noteText) < 5) {
            $m = [
                'mistake_type' => 'poor_notes',
                'severity' => 'medium',
                'message' => 'Notes are too vague or short. Write what the client said, interest level, and any objections.',
                'details' => ['word_count' => str_word_count($noteText)],
            ];
            $mistakes[] = $m;
            AiTrainerService::saveMistake($user, 'leads', 'lead', $lead->id, $m['mistake_type'], $m['severity'], $m['message'], $m['details']);
        }

        // 3. Premature transfer (transferred without notes or missing fields)
        if ($lead->disposition === 'Transferred to Closer') {
            if (empty(trim($noteText))) {
                $m = [
                    'mistake_type' => 'premature_transfer',
                    'severity' => 'high',
                    'message' => 'Lead was transferred without any notes. Closers need context to close deals.',
                    'details' => [],
                ];
                $mistakes[] = $m;
                AiTrainerService::saveMistake($user, 'leads', 'lead', $lead->id, $m['mistake_type'], $m['severity'], $m['message'], $m['details']);
            }
            if (count($missing) > 0) {
                $m = [
                    'mistake_type' => 'transfer_incomplete_data',
                    'severity' => 'high',
                    'message' => 'Lead transferred with missing data: ' . implode(', ', $missing) . '. Complete the data first.',
                    'details' => ['fields' => $missing],
                ];
                $mistakes[] = $m;
                AiTrainerService::saveMistake($user, 'leads', 'lead', $lead->id, $m['mistake_type'], $m['severity'], $m['message'], $m['details']);
            }
        }

        // 4. Skipped statuses (marked Right Number then immediately transferred without callback)
        // This is a soft warning
        if ($lead->disposition === 'Transferred to Closer' && empty($lead->callback_date)) {
            // If transferred same day as creation — might be too fast
            if ($lead->created_at && $lead->updated_at && $lead->created_at->diffInHours($lead->updated_at) < 1) {
                $m = [
                    'mistake_type' => 'rushed_transfer',
                    'severity' => 'low',
                    'message' => 'Lead was created and transferred within 1 hour. Ensure proper qualification was done.',
                    'details' => [],
                ];
                $mistakes[] = $m;
            }
        }

        // 5. Stale undisposed lead
        if (!$lead->disposition && $lead->created_at && $lead->created_at->diffInDays(now()) > 3) {
            $m = [
                'mistake_type' => 'stale_lead',
                'severity' => 'medium',
                'message' => 'This lead is ' . $lead->created_at->diffInDays(now()) . ' days old with no disposition. Call it or mark status.',
                'details' => ['age_days' => $lead->created_at->diffInDays(now())],
            ];
            $mistakes[] = $m;
        }

        return $mistakes;
    }

    public static function detectForDeal(User $user, Deal $deal): array
    {
        $mistakes = [];

        // 1. Missing deal fields
        $missing = [];
        if (empty($deal->owner_name)) $missing[] = 'Owner Name';
        if (empty($deal->primary_phone)) $missing[] = 'Phone';
        if (empty($deal->resort_name)) $missing[] = 'Resort';
        if (empty($deal->fee) || (float) ($deal->fee ?? 0) <= 0) $missing[] = 'Fee';

        if (count($missing) > 0) {
            $m = [
                'mistake_type' => 'missing_deal_fields',
                'severity' => count($missing) >= 3 ? 'high' : 'medium',
                'message' => 'Missing deal fields: ' . implode(', ', $missing),
                'details' => ['fields' => $missing],
            ];
            $mistakes[] = $m;
            AiTrainerService::saveMistake($user, 'deals', 'deal', $deal->id, $m['mistake_type'], $m['severity'], $m['message'], $m['details']);
        }

        // 2. No notes on deal
        $noteText = $deal->notes ?? '';
        if (strlen(trim($noteText)) < 10) {
            $m = [
                'mistake_type' => 'no_deal_notes',
                'severity' => 'medium',
                'message' => 'Deal has no notes or very short notes. Add conversation summary and client agreement details.',
                'details' => [],
            ];
            $mistakes[] = $m;
            AiTrainerService::saveMistake($user, 'deals', 'deal', $deal->id, $m['mistake_type'], $m['severity'], $m['message'], $m['details']);
        }

        // 3. Zero fee deal
        if (!empty($deal->fee) && (float) $deal->fee <= 0) {
            $m = [
                'mistake_type' => 'zero_fee',
                'severity' => 'high',
                'message' => 'Deal fee is $0. This will result in zero commission. Set the correct fee.',
                'details' => ['fee' => $deal->fee],
            ];
            $mistakes[] = $m;
        }

        return $mistakes;
    }
}
