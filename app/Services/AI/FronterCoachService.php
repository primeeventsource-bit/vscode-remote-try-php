<?php

namespace App\Services\AI;

use App\Models\Lead;
use App\Models\User;

/**
 * Live coaching engine for Fronter role.
 * Rule-based — works without OpenAI.
 * Analyzes lead state and tells the fronter exactly what to do.
 */
class FronterCoachService
{
    public static function coachLead(User $user, Lead $lead): array
    {
        $checks = self::runChecklist($lead);
        $tips = [];
        $warnings = [];
        $status = 'good';

        // Missing fields
        $missing = $checks['missing_fields'];
        if (count($missing) > 0) {
            $warnings[] = 'Missing required fields: ' . implode(', ', $missing) . '. Fill these before transferring.';
            $status = 'needs_work';
        }

        // Note quality
        if ($checks['has_notes'] === false) {
            $warnings[] = 'No notes on this lead. Add detailed notes about what the owner said, their interest level, and any objections.';
            $status = 'needs_work';
        } elseif ($checks['note_quality'] === 'poor') {
            $warnings[] = 'Notes are too short or vague. Include: what client said, interest level, objections, and timeline.';
        }

        // Disposition
        if (!$lead->disposition) {
            $tips[] = 'This lead has no disposition. After your call, set a status: Right Number, Wrong Number, Callback, etc.';
        }

        // Transfer readiness
        if ($checks['transfer_ready']) {
            $tips[] = 'This lead looks qualified and ready to transfer. Select a closer and transfer.';
            $status = 'ready';
        } elseif ($lead->disposition === 'Right Number' && count($missing) === 0) {
            $tips[] = 'Owner confirmed — add strong notes about interest and qualification, then transfer.';
        }

        // Callback check
        if ($lead->disposition === 'Callback' && $lead->callback_date) {
            $callbackTime = $lead->callback_date;
            if ($callbackTime->isPast()) {
                $warnings[] = 'Callback is OVERDUE. Call this lead now.';
                $status = 'urgent';
            } else {
                $tips[] = 'Callback scheduled for ' . $callbackTime->format('M j, g:i A') . '. Do not miss it.';
            }
        }

        // Stale lead
        if ($lead->created_at && $lead->created_at->diffInDays(now()) > 7 && !$lead->disposition) {
            $warnings[] = 'This lead is ' . $lead->created_at->diffInDays(now()) . ' days old with no disposition. Call it now or mark status.';
        }

        return [
            'role' => 'fronter',
            'status' => $status,
            'checklist' => $checks,
            'tips' => $tips,
            'warnings' => $warnings,
            'transfer_ready' => $checks['transfer_ready'],
        ];
    }

    public static function nextAction(Lead $lead): array
    {
        $checks = self::runChecklist($lead);

        // Priority 1: Missing phone — can't call
        if (empty($lead->phone1)) {
            return ['action' => 'fill_fields', 'label' => 'Add Phone Number', 'reason' => 'Cannot call this lead without a phone number.', 'priority' => 'high'];
        }

        // Priority 2: No disposition — needs a call
        if (!$lead->disposition) {
            return ['action' => 'call_lead', 'label' => 'Call This Lead', 'reason' => 'This lead has never been called. Dial now and set a disposition.', 'priority' => 'high'];
        }

        // Priority 3: Callback overdue
        if ($lead->disposition === 'Callback' && $lead->callback_date && $lead->callback_date->isPast()) {
            return ['action' => 'call_lead', 'label' => 'Call Back NOW', 'reason' => 'Callback was due ' . $lead->callback_date->diffForHumans() . '. Call immediately.', 'priority' => 'urgent'];
        }

        // Priority 4: Right Number but no notes
        if ($lead->disposition === 'Right Number' && !$checks['has_notes']) {
            return ['action' => 'add_notes', 'label' => 'Add Notes', 'reason' => 'Owner confirmed but notes are missing. Add what they said before transferring.', 'priority' => 'high'];
        }

        // Priority 5: Missing fields
        if (count($checks['missing_fields']) > 0) {
            return ['action' => 'fill_fields', 'label' => 'Complete Lead Data', 'reason' => 'Fill missing: ' . implode(', ', $checks['missing_fields']), 'priority' => 'medium'];
        }

        // Priority 6: Ready to transfer
        if ($checks['transfer_ready']) {
            return ['action' => 'transfer_lead', 'label' => 'Transfer Lead', 'reason' => 'Lead is qualified with complete data and notes. Transfer to a closer.', 'priority' => 'medium'];
        }

        // Priority 7: Callback scheduled
        if ($lead->disposition === 'Callback' && $lead->callback_date && $lead->callback_date->isFuture()) {
            return ['action' => 'wait', 'label' => 'Wait for Callback', 'reason' => 'Callback scheduled for ' . $lead->callback_date->format('M j, g:i A') . '.', 'priority' => 'low'];
        }

        // Priority 8: Dead lead
        if (in_array($lead->disposition, ['Wrong Number', 'Disconnected'])) {
            return ['action' => 'skip', 'label' => 'Move to Next Lead', 'reason' => 'This number is dead. Work your next lead.', 'priority' => 'low'];
        }

        return ['action' => 'review', 'label' => 'Review Lead', 'reason' => 'Check lead details and decide next step.', 'priority' => 'low'];
    }

    public static function runChecklist(Lead $lead): array
    {
        $missing = [];
        if (empty($lead->owner_name)) $missing[] = 'Owner Name';
        if (empty($lead->phone1)) $missing[] = 'Phone 1';
        if (empty($lead->resort)) $missing[] = 'Resort';
        if (empty($lead->city) && empty($lead->st)) $missing[] = 'City/State';

        // Note quality check
        $noteText = $lead->notes ?? '';
        $hasNotes = strlen(trim($noteText)) > 0;
        $noteQuality = 'none';
        if ($hasNotes) {
            $wordCount = str_word_count($noteText);
            $noteQuality = $wordCount >= 15 ? 'good' : ($wordCount >= 5 ? 'fair' : 'poor');
        }

        // Transfer readiness
        $transferReady = count($missing) === 0
            && $hasNotes
            && $noteQuality !== 'poor'
            && in_array($lead->disposition, ['Right Number', 'Callback']);

        return [
            'missing_fields' => $missing,
            'has_notes' => $hasNotes,
            'note_quality' => $noteQuality,
            'note_word_count' => $hasNotes ? str_word_count($noteText) : 0,
            'has_disposition' => !empty($lead->disposition),
            'disposition' => $lead->disposition,
            'has_phone' => !empty($lead->phone1),
            'has_email' => !empty($lead->email),
            'transfer_ready' => $transferReady,
            'lead_age_days' => $lead->created_at ? $lead->created_at->diffInDays(now()) : 0,
        ];
    }
}
