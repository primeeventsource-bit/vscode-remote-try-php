<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\PipelineEvent;
use App\Models\User;

/**
 * Single entry point for writing pipeline events.
 * Every state transition in the CRM must go through here.
 */
class PipelineEventService
{
    public static function logTransferredToCloser(Lead $lead, User $fronter, User $closer): void
    {
        self::log([
            'lead_id' => $lead->id,
            'event_type' => PipelineEvent::TRANSFERRED_TO_CLOSER,
            'from_stage' => 'fronter_working',
            'to_stage' => 'transferred_to_closer',
            'performed_by_user_id' => $fronter->id,
            'source_user_id' => $fronter->id,
            'target_user_id' => $closer->id,
            'source_role' => $fronter->role,
            'target_role' => $closer->role,
        ]);
    }

    public static function logCloserClosedDeal(Lead $lead, Deal $deal, User $closer): void
    {
        self::log([
            'lead_id' => $lead->id,
            'deal_id' => $deal->id,
            'event_type' => PipelineEvent::CLOSER_CLOSED_DEAL,
            'from_stage' => 'transferred_to_closer',
            'to_stage' => 'closed_deal',
            'performed_by_user_id' => $closer->id,
            'source_user_id' => $closer->id,
            'source_role' => $closer->role,
            'success_flag' => true,
        ]);
    }

    public static function logCloserNotClosed(Lead $lead, User $closer, ?string $reason = null): void
    {
        self::log([
            'lead_id' => $lead->id,
            'event_type' => PipelineEvent::CLOSER_NOT_CLOSED,
            'from_stage' => 'transferred_to_closer',
            'to_stage' => 'not_closed',
            'performed_by_user_id' => $closer->id,
            'source_user_id' => $closer->id,
            'source_role' => $closer->role,
            'success_flag' => false,
            'outcome' => 'no_deal',
            'notes' => $reason,
        ]);
    }

    public static function logSentToVerification(Deal $deal, User $closer, User $admin): void
    {
        self::log([
            'lead_id' => $deal->lead_id,
            'deal_id' => $deal->id,
            'event_type' => PipelineEvent::SENT_TO_VERIFICATION,
            'from_stage' => 'closed_deal',
            'to_stage' => 'sent_to_verification',
            'performed_by_user_id' => $closer->id,
            'source_user_id' => $closer->id,
            'target_user_id' => $admin->id,
            'source_role' => $closer->role,
            'target_role' => $admin->role,
        ]);
    }

    public static function logVerificationChargedGreen(Deal $deal, User $admin): void
    {
        self::log([
            'lead_id' => $deal->lead_id,
            'deal_id' => $deal->id,
            'event_type' => PipelineEvent::VERIFICATION_CHARGED_GREEN,
            'from_stage' => 'sent_to_verification',
            'to_stage' => 'charged_green',
            'performed_by_user_id' => $admin->id,
            'target_user_id' => $admin->id,
            'target_role' => $admin->role,
            'success_flag' => true,
            'outcome' => 'charged',
        ]);
    }

    public static function logVerificationNotCharged(Deal $deal, User $admin, ?string $reason = null): void
    {
        self::log([
            'lead_id' => $deal->lead_id,
            'deal_id' => $deal->id,
            'event_type' => PipelineEvent::VERIFICATION_NOT_CHARGED,
            'from_stage' => 'sent_to_verification',
            'to_stage' => 'not_charged',
            'performed_by_user_id' => $admin->id,
            'target_user_id' => $admin->id,
            'target_role' => $admin->role,
            'success_flag' => false,
            'outcome' => 'not_charged',
            'notes' => $reason,
        ]);
    }

    private static function log(array $data): void
    {
        try {
            $data['event_at'] = $data['event_at'] ?? now();
            PipelineEvent::create($data);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
