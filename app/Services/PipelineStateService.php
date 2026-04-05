<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single entry point for ALL pipeline state transitions.
 * Every method:
 *   1. Updates lead/deal current state (only columns that exist)
 *   2. Logs pipeline event
 *   3. Wraps in DB::transaction
 *
 * All methods are migration-safe: they check column existence before writing
 * new pipeline fields, so the app works before AND after migrations run.
 */
class PipelineStateService
{
    private static ?bool $leadsHasPipeline = null;
    private static ?bool $dealsHasPipeline = null;

    private static function leadsReady(): bool
    {
        if (self::$leadsHasPipeline === null) {
            try {
                self::$leadsHasPipeline = Schema::hasColumn('leads', 'current_stage');
            } catch (\Throwable $e) {
                self::$leadsHasPipeline = false;
            }
        }
        return self::$leadsHasPipeline;
    }

    private static function dealsReady(): bool
    {
        if (self::$dealsHasPipeline === null) {
            try {
                self::$dealsHasPipeline = Schema::hasColumn('deals', 'charge_status');
            } catch (\Throwable $e) {
                self::$dealsHasPipeline = false;
            }
        }
        return self::$dealsHasPipeline;
    }

    /**
     * Fronter transfers lead to closer.
     */
    public static function transferLeadToCloser(Lead $lead, User $fronter, User $closer): void
    {
        DB::transaction(function () use ($lead, $fronter, $closer) {
            // Core fields (always exist)
            $data = [
                'disposition' => 'Transferred to Closer',
                'transferred_to' => (string) $closer->id,
                'assigned_to' => $closer->id,
                'original_fronter' => $lead->original_fronter ?? $fronter->id,
            ];

            // Pipeline fields (only after migration)
            if (self::leadsReady()) {
                $data['current_stage'] = 'transferred_to_closer';
                $data['transferred_by_user_id'] = $fronter->id;
                $data['transferred_to_user_id'] = $closer->id;
                $data['transferred_at'] = now();
            }

            $lead->update($data);
            PipelineEventService::logTransferredToCloser($lead, $fronter, $closer);
        });
    }

    /**
     * Closer acknowledges receipt of transfer.
     */
    public static function markCloserReceived(Lead $lead, User $closer): void
    {
        if (!self::leadsReady()) return;

        DB::transaction(function () use ($lead, $closer) {
            $lead->update([
                'current_stage' => 'closer_working',
                'closer_received_at' => now(),
            ]);
        });
    }

    /**
     * Closer converts lead into a deal.
     */
    public static function closeLeadIntoDeal(Lead $lead, User $closer, array $dealData): Deal
    {
        return DB::transaction(function () use ($lead, $closer, $dealData) {
            // Core deal fields (always exist)
            $dealData['closer'] = $closer->id;
            $dealData['fronter'] = $lead->original_fronter ?? $lead->assigned_to;
            $dealData['status'] = $dealData['status'] ?? 'pending_admin';
            $dealData['charged'] = $dealData['charged'] ?? 'no';
            $dealData['charged_back'] = $dealData['charged_back'] ?? 'no';

            // Pipeline fields on deal (only after migration)
            if (self::dealsReady()) {
                $dealData['lead_id'] = $lead->id;
                $dealData['closer_user_id'] = $closer->id;
            } elseif (Schema::hasColumn('deals', 'lead_id')) {
                $dealData['lead_id'] = $lead->id;
            }

            $deal = Deal::create($dealData);

            // Core lead update
            $leadData = ['disposition' => 'Converted to Deal'];

            // Pipeline fields on lead (only after migration)
            if (self::leadsReady()) {
                $leadData['current_stage'] = 'closed_deal';
                $leadData['closed_by_user_id'] = $closer->id;
                $leadData['closed_at'] = now();
                $leadData['converted_to_deal_id'] = $deal->id;
                $leadData['final_outcome'] = 'deal_created';
                $leadData['final_outcome_at'] = now();
            }

            $lead->update($leadData);
            PipelineEventService::logCloserClosedDeal($lead, $deal, $closer);

            return $deal;
        });
    }

    /**
     * Closer did not close the lead.
     */
    public static function markCloserNotClosed(Lead $lead, User $closer, ?string $reason = null): void
    {
        DB::transaction(function () use ($lead, $closer, $reason) {
            if (self::leadsReady()) {
                $lead->update([
                    'current_stage' => 'not_closed',
                    'final_outcome' => 'no_deal',
                    'final_outcome_at' => now(),
                ]);
            }

            PipelineEventService::logCloserNotClosed($lead, $closer, $reason);
        });
    }

    /**
     * Closer sends deal to verification/admin.
     */
    public static function sendToVerification(Deal $deal, User $closer, User $admin): void
    {
        DB::transaction(function () use ($deal, $closer, $admin) {
            // Core fields (always exist)
            $dealData = [
                'assigned_admin' => $admin->id,
                'status' => 'in_verification',
            ];

            // Pipeline fields (only after migration)
            if (self::dealsReady()) {
                $dealData['verification_admin_user_id'] = $admin->id;
                $dealData['verification_status'] = 'pending';
                $dealData['sent_to_verification_by_user_id'] = $closer->id;
                $dealData['sent_to_verification_at'] = now();
            }

            $deal->update($dealData);

            // Update lead if linked and pipeline columns exist
            if ($deal->lead_id && self::leadsReady()) {
                Lead::where('id', $deal->lead_id)->update([
                    'current_stage' => 'sent_to_verification',
                    'sent_to_verification_by_user_id' => $closer->id,
                    'sent_to_verification_at' => now(),
                    'verification_received_by_user_id' => $admin->id,
                ]);
            }

            PipelineEventService::logSentToVerification($deal, $closer, $admin);
        });
    }

    /**
     * Admin acknowledges receipt of deal for verification.
     */
    public static function markVerificationReceived(Deal $deal, User $admin): void
    {
        DB::transaction(function () use ($deal, $admin) {
            if (self::dealsReady()) {
                $deal->update([
                    'verification_status' => 'received',
                    'verification_admin_user_id' => $admin->id,
                    'verification_received_at' => now(),
                ]);
            }

            if ($deal->lead_id && self::leadsReady()) {
                Lead::where('id', $deal->lead_id)->update([
                    'current_stage' => 'verification_working',
                    'verification_received_by_user_id' => $admin->id,
                    'verification_received_at' => now(),
                ]);
            }
        });
    }

    /**
     * Admin charges deal — turns GREEN.
     */
    public static function markChargedGreen(Deal $deal, User $admin): void
    {
        DB::transaction(function () use ($deal, $admin) {
            // Core fields (always exist)
            $dealData = [
                'status' => 'charged',
                'charged' => 'yes',
                'charged_date' => now()->format('Y-m-d'),
                'is_locked' => true,
            ];

            // Pipeline fields (only after migration)
            if (self::dealsReady()) {
                $dealData['charge_status'] = 'charged';
                $dealData['verification_status'] = 'charged';
                $dealData['charged_by_user_id'] = $admin->id;
                $dealData['charged_at'] = now();
                $dealData['is_green'] = true;
            }

            $deal->update($dealData);

            if ($deal->lead_id && self::leadsReady()) {
                Lead::where('id', $deal->lead_id)->update([
                    'current_stage' => 'charged_green',
                    'final_outcome' => 'charged_green',
                    'final_outcome_at' => now(),
                ]);
            }

            PipelineEventService::logVerificationChargedGreen($deal, $admin);

            try {
                \App\Services\CommissionCalculator::calculate($deal);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * Admin does NOT charge — deal fails verification.
     */
    public static function markNotCharged(Deal $deal, User $admin, ?string $reason = null): void
    {
        DB::transaction(function () use ($deal, $admin, $reason) {
            // Core fields (always exist)
            $dealData = ['status' => 'cancelled'];

            // Pipeline fields (only after migration)
            if (self::dealsReady()) {
                $dealData['charge_status'] = 'not_charged';
                $dealData['verification_status'] = 'not_charged';
                $dealData['charged_by_user_id'] = $admin->id;
                $dealData['charged_at'] = now();
                $dealData['is_green'] = false;
            }

            $deal->update($dealData);

            if ($deal->lead_id && self::leadsReady()) {
                Lead::where('id', $deal->lead_id)->update([
                    'current_stage' => 'not_charged',
                    'final_outcome' => 'not_charged',
                    'final_outcome_at' => now(),
                ]);
            }

            PipelineEventService::logVerificationNotCharged($deal, $admin, $reason);
        });
    }

    /**
     * Closer transfers deal to another closer.
     */
    public static function transferDealToCloser(Deal $deal, User $fromCloser, User $toCloser, string $note): void
    {
        DB::transaction(function () use ($deal, $fromCloser, $toCloser, $note) {
            // Update deal ownership
            $dealData = ['closer' => $toCloser->id];
            if (self::dealsReady()) {
                $dealData['closer_user_id'] = $toCloser->id;
            }
            $deal->update($dealData);

            // Update linked lead if exists
            if ($deal->lead_id) {
                $leadData = [
                    'assigned_to' => $toCloser->id,
                    'transferred_to' => (string) $toCloser->id,
                ];
                if (self::leadsReady()) {
                    $leadData['transferred_by_user_id'] = $fromCloser->id;
                    $leadData['transferred_to_user_id'] = $toCloser->id;
                    $leadData['transferred_at'] = now();
                }
                Lead::where('id', $deal->lead_id)->update($leadData);
            }

            // Log pipeline event
            PipelineEventService::logCloserTransferredToCloser($deal, $fromCloser, $toCloser, $note);
        });
    }
}
