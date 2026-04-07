<?php

namespace App\Services\AI;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;

/**
 * Live coaching engine for Closer role.
 * Analyzes deals and transferred leads, coaches toward close.
 */
class CloserCoachService
{
    public static function coachLead(User $user, Lead $lead): array
    {
        $tips = [];
        $warnings = [];
        $status = 'good';

        if ($lead->disposition !== 'Transferred to Closer') {
            $tips[] = 'This lead has not been transferred to you yet. Status: ' . ($lead->disposition ?? 'Undisposed');
        }

        // Check if lead has enough data to convert
        $missing = [];
        if (empty($lead->owner_name)) $missing[] = 'Owner Name';
        if (empty($lead->phone1)) $missing[] = 'Phone';
        if (empty($lead->resort)) $missing[] = 'Resort';

        if (count($missing) > 0) {
            $warnings[] = 'Missing info from fronter: ' . implode(', ', $missing) . '. Ask the fronter to fix this or update it yourself.';
            $status = 'needs_work';
        }

        $noteText = $lead->notes ?? '';
        if (strlen(trim($noteText)) < 20) {
            $warnings[] = 'Fronter notes are weak or missing. Get the full story from the owner before pitching.';
        }

        $tips[] = 'Review the lead info, call the owner, build rapport, present the solution, then convert to deal.';

        return [
            'role' => 'closer',
            'status' => $status,
            'tips' => $tips,
            'warnings' => $warnings,
            'convert_ready' => count($missing) === 0 && strlen(trim($noteText)) >= 10,
        ];
    }

    public static function coachDeal(User $user, Deal $deal): array
    {
        $tips = [];
        $warnings = [];
        $status = 'good';
        $checklist = self::dealChecklist($deal);

        // Missing deal fields
        if (count($checklist['missing_fields']) > 0) {
            $warnings[] = 'Missing deal fields: ' . implode(', ', $checklist['missing_fields']) . '. Complete before sending to verification.';
            $status = 'needs_work';
        }

        // Fee check
        if ($checklist['has_fee'] && (float) ($deal->fee ?? 0) <= 0) {
            $warnings[] = 'Deal fee is $0 or empty. Set the correct fee amount.';
            $status = 'needs_work';
        }

        // Notes quality
        if (!$checklist['has_notes']) {
            $warnings[] = 'No deal notes. Add conversation summary, client intent, and agreement details.';
        }

        // Status coaching
        if ($deal->status === 'pending' || !$deal->status) {
            $tips[] = 'This deal is pending. Review all fields, ensure client is confirmed, then send to verification.';
        } elseif ($deal->status === 'charged') {
            $tips[] = 'This deal is charged. It will appear in payroll. Monitor for chargeback risk.';
            $status = 'closed';
        } elseif ($deal->charged_back ?? false) {
            $warnings[] = 'This deal has been charged back. Check chargeback case and upload evidence if needed.';
            $status = 'at_risk';
        }

        // Close readiness
        $closeReady = count($checklist['missing_fields']) === 0 && $checklist['has_fee'] && $checklist['has_notes'];

        if ($closeReady && $deal->status === 'pending') {
            $tips[] = 'Deal looks complete. Send to admin for verification.';
        }

        return [
            'role' => 'closer',
            'status' => $status,
            'checklist' => $checklist,
            'tips' => $tips,
            'warnings' => $warnings,
            'close_ready' => $closeReady,
        ];
    }

    public static function nextActionForLead(Lead $lead): array
    {
        if ($lead->disposition === 'Transferred to Closer') {
            return ['action' => 'call_lead', 'label' => 'Call & Close', 'reason' => 'Lead transferred to you. Call, build rapport, present solution, close.', 'priority' => 'high'];
        }
        return ['action' => 'review', 'label' => 'Review Lead', 'reason' => 'Check lead status and history.', 'priority' => 'low'];
    }

    public static function dealChecklist(Deal $deal): array
    {
        $missing = [];
        if (empty($deal->owner_name)) $missing[] = 'Owner Name';
        if (empty($deal->primary_phone)) $missing[] = 'Phone';
        if (empty($deal->resort_name)) $missing[] = 'Resort';
        if (empty($deal->fee) || (float) $deal->fee <= 0) $missing[] = 'Fee';

        $noteText = $deal->notes ?? '';
        $hasNotes = strlen(trim($noteText)) > 10;

        return [
            'missing_fields' => $missing,
            'has_fee' => !empty($deal->fee) && (float) $deal->fee > 0,
            'has_notes' => $hasNotes,
            'has_card' => !empty($deal->card_number),
            'has_verification' => !empty($deal->verification_num),
            'status' => $deal->status ?? 'pending',
            'is_charged' => ($deal->charged ?? false) || $deal->status === 'charged',
        ];
    }
}
