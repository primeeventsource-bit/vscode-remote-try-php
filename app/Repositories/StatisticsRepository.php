<?php

namespace App\Repositories;

use App\Models\PipelineEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Centralized statistics queries.
 * All methods return plain arrays — never stdClass, never Eloquent models.
 * All percentages are safe against division by zero.
 */
class StatisticsRepository
{
    /**
     * Check if pipeline_events table exists (pre-migration safety).
     */
    private static function tableReady(): bool
    {
        try {
            return Schema::hasTable('pipeline_events');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function safePct(int|float $numerator, int|float $denominator, int $precision = 1): float
    {
        if ($denominator == 0) return 0.0;
        return round($numerator / $denominator * 100, $precision);
    }

    // ══════════════════════════════════════════════════════════════
    // FRONTER STATS
    // ══════════════════════════════════════════════════════════════

    /**
     * For each fronter:
     * - transfers_sent: leads they transferred to a closer
     * - deals_closed: how many of their transferred leads became deals
     * - no_deals: transfers_sent - deals_closed
     * - close_pct: deals_closed / transfers_sent * 100
     * - no_deal_pct: no_deals / transfers_sent * 100
     */
    public static function getFronterStats($from = null, $to = null, ?int $userId = null): array
    {
        if (!self::tableReady()) return self::fronterStatsFallback($from, $to, $userId);

        $query = User::whereIn('role', ['fronter', 'fronter_panama'])->orderBy('name');
        if ($userId) $query->where('id', $userId);
        $fronters = $query->get();
        $result = [];

        foreach ($fronters as $f) {
            // Step 1: TRANSFER COHORT — distinct lead IDs this fronter sent
            $sentLeadIds = DB::table('pipeline_events')
                ->where('event_type', PipelineEvent::TRANSFERRED_TO_CLOSER)
                ->where('source_user_id', $f->id)
                ->when($from, fn($q) => $q->where('event_at', '>=', $from))
                ->when($to, fn($q) => $q->where('event_at', '<=', $to))
                ->whereNotNull('lead_id')
                ->distinct()
                ->pluck('lead_id')
                ->toArray();

            $transfersSent = count($sentLeadIds);

            // Step 2: From that SAME cohort, count leads that got closed
            $dealsClosed = 0;
            if ($transfersSent > 0) {
                $dealsClosed = DB::table('pipeline_events')
                    ->where('event_type', PipelineEvent::CLOSER_CLOSED_DEAL)
                    ->whereIn('lead_id', $sentLeadIds)
                    ->distinct('lead_id')
                    ->count('lead_id');
            }

            // Safeguard
            $dealsClosed = min($dealsClosed, $transfersSent);
            $noDeals = max(0, $transfersSent - $dealsClosed);

            $result[] = [
                'user_id' => $f->id,
                'name' => $f->name,
                'avatar' => $f->avatar ?? strtoupper(substr($f->name, 0, 2)),
                'color' => $f->color ?? '#6b7280',
                'role' => $f->role,
                'location' => str_contains($f->role, 'panama') ? 'Panama' : 'US',
                'transfers_sent' => $transfersSent,
                'deals_closed' => $dealsClosed,
                'no_deals' => $noDeals,
                'close_pct' => self::safePct($dealsClosed, $transfersSent),
                'no_deal_pct' => self::safePct($noDeals, $transfersSent),
            ];
        }

        return $result;
    }

    /**
     * Fallback when pipeline_events table doesn't exist yet.
     * Uses existing leads/deals tables directly.
     */
    private static function fronterStatsFallback($from, $to, ?int $userId = null): array
    {
        $query = User::whereIn('role', ['fronter', 'fronter_panama'])->orderBy('name');
        if ($userId) $query->where('id', $userId);
        $fronters = $query->get();
        $result = [];

        foreach ($fronters as $f) {
            // Cohort: leads transferred by this fronter
            $tQuery = DB::table('leads')
                ->where('disposition', 'Transferred to Closer')
                ->where(function ($q) use ($f) {
                    $q->where('original_fronter', $f->id)
                      ->orWhere('assigned_to', $f->id);
                });
            if ($from) $tQuery->where('updated_at', '>=', $from);
            if ($to) $tQuery->where('updated_at', '<=', $to);

            $transferredLeadIds = $tQuery->pluck('id')->toArray();
            $transfersSent = count($transferredLeadIds);

            // From that cohort, count leads that became deals
            $dealsClosed = 0;
            if ($transfersSent > 0) {
                $leadNames = DB::table('leads')->whereIn('id', $transferredLeadIds)->pluck('owner_name')->toArray();
                $dealsQuery = DB::table('deals')->where('fronter', $f->id);
                if (!empty($leadNames)) {
                    $dealsQuery->where(function ($q) use ($transferredLeadIds, $leadNames) {
                        $q->whereIn('lead_id', $transferredLeadIds)
                          ->orWhereIn('owner_name', $leadNames);
                    });
                }
                $dealsClosed = $dealsQuery->count();
            }

            // Safeguard
            $dealsClosed = min($dealsClosed, $transfersSent);
            $noDeals = max(0, $transfersSent - $dealsClosed);

            $result[] = [
                'user_id' => $f->id,
                'name' => $f->name,
                'avatar' => $f->avatar ?? strtoupper(substr($f->name, 0, 2)),
                'color' => $f->color ?? '#6b7280',
                'role' => $f->role,
                'location' => str_contains($f->role, 'panama') ? 'Panama' : 'US',
                'transfers_sent' => $transfersSent,
                'deals_closed' => $dealsClosed,
                'no_deals' => $noDeals,
                'close_pct' => self::safePct($dealsClosed, $transfersSent),
                'no_deal_pct' => self::safePct($noDeals, $transfersSent),
            ];
        }

        return $result;
    }

    // ══════════════════════════════════════════════════════════════
    // CLOSER STATS
    // ══════════════════════════════════════════════════════════════

    /**
     * For each closer:
     * - transfers_received: transfers received from fronters
     * - deals_closed: transfers turned into deals
     * - sent_to_verification: closed deals sent to admin
     * - not_closed: received but not turned into deals
     * - close_pct, no_close_pct, verification_pct
     */
    public static function getCloserStats($from = null, $to = null, ?int $userId = null): array
    {
        if (!self::tableReady()) return self::closerStatsFallback($from, $to, $userId);

        $query = User::whereIn('role', ['closer', 'closer_panama'])->orderBy('name');
        if ($userId) $query->where('id', $userId);
        $closers = $query->get();
        $result = [];

        foreach ($closers as $c) {
            // Step 1: Get the RECEIVED TRANSFER COHORT — distinct lead IDs
            $receivedLeadIds = DB::table('pipeline_events')
                ->where('event_type', PipelineEvent::TRANSFERRED_TO_CLOSER)
                ->where('target_user_id', $c->id)
                ->when($from, fn($q) => $q->where('event_at', '>=', $from))
                ->when($to, fn($q) => $q->where('event_at', '<=', $to))
                ->whereNotNull('lead_id')
                ->distinct()
                ->pluck('lead_id')
                ->toArray();

            $transfersReceived = count($receivedLeadIds);

            if ($transfersReceived === 0) {
                $result[] = self::emptyCloserRow($c);
                continue;
            }

            // Step 2: From that SAME cohort, count deals closed
            $dealsClosed = DB::table('pipeline_events')
                ->where('event_type', PipelineEvent::CLOSER_CLOSED_DEAL)
                ->where('performed_by_user_id', $c->id)
                ->whereIn('lead_id', $receivedLeadIds)
                ->distinct('lead_id')
                ->count('lead_id');

            // Step 3: From that SAME closed subset, count sent to verification
            $closedLeadIds = DB::table('pipeline_events')
                ->where('event_type', PipelineEvent::CLOSER_CLOSED_DEAL)
                ->where('performed_by_user_id', $c->id)
                ->whereIn('lead_id', $receivedLeadIds)
                ->whereNotNull('deal_id')
                ->distinct()
                ->pluck('deal_id')
                ->toArray();

            $sentToVerification = 0;
            if (!empty($closedLeadIds)) {
                $sentToVerification = DB::table('pipeline_events')
                    ->where('event_type', PipelineEvent::SENT_TO_VERIFICATION)
                    ->where('source_user_id', $c->id)
                    ->whereIn('deal_id', $closedLeadIds)
                    ->distinct('deal_id')
                    ->count('deal_id');
            }

            // Step 4: Not closed = from cohort with closer_not_closed event
            $notClosedExplicit = DB::table('pipeline_events')
                ->where('event_type', PipelineEvent::CLOSER_NOT_CLOSED)
                ->where('performed_by_user_id', $c->id)
                ->whereIn('lead_id', $receivedLeadIds)
                ->distinct('lead_id')
                ->count('lead_id');

            // Fallback: not_closed = transfers - deals (but never negative)
            $notClosed = max($notClosedExplicit, $transfersReceived - $dealsClosed);

            // Safeguard: deals_closed cannot exceed transfers_received
            $dealsClosed = min($dealsClosed, $transfersReceived);
            $sentToVerification = min($sentToVerification, $dealsClosed);
            $notClosed = max(0, $transfersReceived - $dealsClosed);

            $result[] = [
                'user_id' => $c->id,
                'name' => $c->name,
                'avatar' => $c->avatar ?? strtoupper(substr($c->name, 0, 2)),
                'color' => $c->color ?? '#6b7280',
                'role' => $c->role,
                'location' => str_contains($c->role, 'panama') ? 'Panama' : 'US',
                'transfers_received' => $transfersReceived,
                'deals_closed' => $dealsClosed,
                'sent_to_verification' => $sentToVerification,
                'not_closed' => $notClosed,
                'close_pct' => self::safePct($dealsClosed, $transfersReceived),
                'no_close_pct' => self::safePct($notClosed, $transfersReceived),
                'verification_pct' => self::safePct($sentToVerification, $dealsClosed),
            ];
        }

        return $result;
    }

    private static function emptyCloserRow(User $c): array
    {
        return [
            'user_id' => $c->id,
            'name' => $c->name,
            'avatar' => $c->avatar ?? strtoupper(substr($c->name, 0, 2)),
            'color' => $c->color ?? '#6b7280',
            'role' => $c->role,
            'location' => str_contains($c->role, 'panama') ? 'Panama' : 'US',
            'transfers_received' => 0,
            'deals_closed' => 0,
            'sent_to_verification' => 0,
            'not_closed' => 0,
            'close_pct' => 0.0,
            'no_close_pct' => 0.0,
            'verification_pct' => 0.0,
        ];
    }

    private static function closerStatsFallback($from, $to, ?int $userId = null): array
    {
        $query = User::whereIn('role', ['closer', 'closer_panama'])->orderBy('name');
        if ($userId) $query->where('id', $userId);
        $closers = $query->get();
        $result = [];

        foreach ($closers as $c) {
            // Cohort: leads transferred to this closer
            $recvQuery = DB::table('leads')
                ->where('disposition', 'Transferred to Closer')
                ->where('transferred_to', (string) $c->id);
            if ($from) $recvQuery->where('updated_at', '>=', $from);
            if ($to) $recvQuery->where('updated_at', '<=', $to);

            $receivedLeadIds = $recvQuery->pluck('id')->toArray();
            $transfersReceived = count($receivedLeadIds);

            if ($transfersReceived === 0) {
                $result[] = self::emptyCloserRow($c);
                continue;
            }

            // Deals from leads in this cohort (via lead_id or owner_name match)
            $dealsQuery = DB::table('deals')->where('closer', $c->id);
            if (!empty($receivedLeadIds)) {
                $leadNames = DB::table('leads')->whereIn('id', $receivedLeadIds)->pluck('owner_name')->toArray();
                $dealsQuery->where(function ($q) use ($receivedLeadIds, $leadNames) {
                    $q->whereIn('lead_id', $receivedLeadIds);
                    if (!empty($leadNames)) {
                        $q->orWhereIn('owner_name', $leadNames);
                    }
                });
            }
            $dealsClosed = $dealsQuery->count();

            $verifQuery = DB::table('deals')
                ->where('closer', $c->id)
                ->whereNotNull('assigned_admin')
                ->whereIn('status', ['in_verification', 'charged', 'chargeback', 'chargeback_won', 'chargeback_lost']);
            if (!empty($receivedLeadIds)) {
                $verifQuery->where(function ($q) use ($receivedLeadIds, $leadNames) {
                    $q->whereIn('lead_id', $receivedLeadIds);
                    if (!empty($leadNames)) {
                        $q->orWhereIn('owner_name', $leadNames);
                    }
                });
            }
            $sentToVerification = $verifQuery->count();

            // Safeguards
            $dealsClosed = min($dealsClosed, $transfersReceived);
            $sentToVerification = min($sentToVerification, $dealsClosed);
            $notClosed = max(0, $transfersReceived - $dealsClosed);

            $result[] = [
                'user_id' => $c->id,
                'name' => $c->name,
                'avatar' => $c->avatar ?? strtoupper(substr($c->name, 0, 2)),
                'color' => $c->color ?? '#6b7280',
                'role' => $c->role,
                'location' => str_contains($c->role, 'panama') ? 'Panama' : 'US',
                'transfers_received' => $transfersReceived,
                'deals_closed' => $dealsClosed,
                'sent_to_verification' => $sentToVerification,
                'not_closed' => $notClosed,
                'close_pct' => self::safePct($dealsClosed, $transfersReceived),
                'no_close_pct' => self::safePct($notClosed, $transfersReceived),
                'verification_pct' => self::safePct($sentToVerification, $dealsClosed),
            ];
        }

        return $result;
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN / VERIFICATION STATS
    // ══════════════════════════════════════════════════════════════

    /**
     * For each admin:
     * - received: deals received for verification
     * - charged_green: successfully charged
     * - not_charged: not charged / cancelled / failed
     * - charge_pct, not_charged_pct
     */
    public static function getAdminStats($from = null, $to = null, ?int $userId = null): array
    {
        if (!self::tableReady()) return self::adminStatsFallback($from, $to, $userId);

        $query = User::whereIn('role', ['admin', 'master_admin', 'admin_limited'])->orderBy('name');
        if ($userId) $query->where('id', $userId);
        $admins = $query->get();
        $result = [];

        foreach ($admins as $a) {
            $received = PipelineEvent::eventType(PipelineEvent::SENT_TO_VERIFICATION)
                ->forTargetUser($a->id)
                ->inRange($from, $to)
                ->count();

            $chargedGreen = PipelineEvent::eventType(PipelineEvent::VERIFICATION_CHARGED_GREEN)
                ->forPerformer($a->id)
                ->inRange($from, $to)
                ->count();

            $notCharged = PipelineEvent::eventType(PipelineEvent::VERIFICATION_NOT_CHARGED)
                ->forPerformer($a->id)
                ->inRange($from, $to)
                ->count();

            $result[] = [
                'user_id' => $a->id,
                'name' => $a->name,
                'avatar' => $a->avatar ?? strtoupper(substr($a->name, 0, 2)),
                'color' => $a->color ?? '#6b7280',
                'received' => $received,
                'charged_green' => $chargedGreen,
                'not_charged' => $notCharged,
                'charge_pct' => self::safePct($chargedGreen, $received),
                'not_charged_pct' => self::safePct($notCharged, $received),
            ];
        }

        return $result;
    }

    private static function adminStatsFallback($from, $to, ?int $userId = null): array
    {
        $query = User::whereIn('role', ['admin', 'master_admin', 'admin_limited'])->orderBy('name');
        if ($userId) $query->where('id', $userId);
        $admins = $query->get();
        $result = [];

        foreach ($admins as $a) {
            $recvQuery = DB::table('deals')
                ->where('assigned_admin', $a->id)
                ->whereIn('status', ['in_verification', 'charged', 'chargeback', 'chargeback_won', 'chargeback_lost', 'cancelled']);
            if ($from) $recvQuery->where('created_at', '>=', $from);
            if ($to) $recvQuery->where('created_at', '<=', $to);
            $received = $recvQuery->count();

            $chargedQuery = DB::table('deals')
                ->where('assigned_admin', $a->id)
                ->where('charged', 'yes');
            if ($from) $chargedQuery->where('charged_date', '>=', $from);
            if ($to) $chargedQuery->where('charged_date', '<=', $to);
            $chargedGreen = $chargedQuery->count();

            $notChargedQuery = DB::table('deals')
                ->where('assigned_admin', $a->id)
                ->where('charged', '!=', 'yes')
                ->where('status', 'cancelled');
            if ($from) $notChargedQuery->where('updated_at', '>=', $from);
            if ($to) $notChargedQuery->where('updated_at', '<=', $to);
            $notCharged = $notChargedQuery->count();

            $result[] = [
                'user_id' => $a->id,
                'name' => $a->name,
                'avatar' => $a->avatar ?? strtoupper(substr($a->name, 0, 2)),
                'color' => $a->color ?? '#6b7280',
                'received' => $received,
                'charged_green' => $chargedGreen,
                'not_charged' => $notCharged,
                'charge_pct' => self::safePct($chargedGreen, $received),
                'not_charged_pct' => self::safePct($notCharged, $received),
            ];
        }

        return $result;
    }

    // ══════════════════════════════════════════════════════════════
    // OVERALL PIPELINE SUMMARY
    // ══════════════════════════════════════════════════════════════

    public static function getOverallSummary($from = null, $to = null): array
    {
        if (!self::tableReady()) return self::overallSummaryFallback($from, $to);

        $q = fn(string $type) => PipelineEvent::eventType($type)->inRange($from, $to)->count();

        $totalTransfers = $q(PipelineEvent::TRANSFERRED_TO_CLOSER);
        $totalDealsClosed = $q(PipelineEvent::CLOSER_CLOSED_DEAL);
        $totalSentToVerif = $q(PipelineEvent::SENT_TO_VERIFICATION);
        $totalCharged = $q(PipelineEvent::VERIFICATION_CHARGED_GREEN);
        $totalNotCharged = $q(PipelineEvent::VERIFICATION_NOT_CHARGED);

        return [
            'total_transfers' => $totalTransfers,
            'total_deals_closed' => $totalDealsClosed,
            'total_sent_to_verification' => $totalSentToVerif,
            'total_charged_green' => $totalCharged,
            'total_not_charged' => $totalNotCharged,
            'transfer_to_deal_pct' => self::safePct($totalDealsClosed, $totalTransfers),
            'deal_to_verification_pct' => self::safePct($totalSentToVerif, $totalDealsClosed),
            'verification_charge_pct' => self::safePct($totalCharged, $totalSentToVerif),
            'overall_conversion_pct' => self::safePct($totalCharged, $totalTransfers),
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // PERSONAL DASHBOARD STATS (scoped to single user)
    // ══════════════════════════════════════════════════════════════

    /**
     * Fronter's personal dashboard stats.
     */
    public static function getFronterDashboardStatsForUser(User $user, $from = null, $to = null): array
    {
        if (self::tableReady()) {
            $transfersSent = PipelineEvent::eventType(PipelineEvent::TRANSFERRED_TO_CLOSER)
                ->forSourceUser($user->id)->inRange($from, $to)->count();

            $dealsClosed = DB::table('pipeline_events as closed')
                ->join('pipeline_events as transferred', function ($join) {
                    $join->on('closed.lead_id', '=', 'transferred.lead_id')
                         ->where('transferred.event_type', '=', PipelineEvent::TRANSFERRED_TO_CLOSER);
                })
                ->where('closed.event_type', PipelineEvent::CLOSER_CLOSED_DEAL)
                ->where('transferred.source_user_id', $user->id)
                ->when($from, fn($q) => $q->where('closed.event_at', '>=', $from))
                ->when($to, fn($q) => $q->where('closed.event_at', '<=', $to))
                ->distinct('closed.deal_id')
                ->count('closed.deal_id');
        } else {
            $tq = DB::table('leads')
                ->where('disposition', 'Transferred to Closer')
                ->where(fn($q) => $q->where('original_fronter', $user->id)->orWhere('assigned_to', $user->id));
            if ($from) $tq->where('updated_at', '>=', $from);
            if ($to) $tq->where('updated_at', '<=', $to);
            $transfersSent = $tq->count();

            $dq = DB::table('deals')->where('fronter', $user->id);
            if ($from) $dq->where('created_at', '>=', $from);
            if ($to) $dq->where('created_at', '<=', $to);
            $dealsClosed = $dq->count();
        }

        $noDeals = max(0, $transfersSent - $dealsClosed);
        return [
            'transfers_sent' => $transfersSent,
            'deals_closed' => $dealsClosed,
            'no_deals' => $noDeals,
            'close_pct' => self::safePct($dealsClosed, $transfersSent),
            'no_deal_pct' => self::safePct($noDeals, $transfersSent),
        ];
    }

    /**
     * Closer's personal dashboard stats.
     */
    public static function getCloserDashboardStatsForUser(User $user, $from = null, $to = null): array
    {
        if (self::tableReady()) {
            $transfersReceived = PipelineEvent::eventType(PipelineEvent::TRANSFERRED_TO_CLOSER)
                ->forTargetUser($user->id)->inRange($from, $to)->count();
            $dealsClosed = PipelineEvent::eventType(PipelineEvent::CLOSER_CLOSED_DEAL)
                ->forPerformer($user->id)->inRange($from, $to)->count();
            $sentToVerification = PipelineEvent::eventType(PipelineEvent::SENT_TO_VERIFICATION)
                ->forSourceUser($user->id)->inRange($from, $to)->count();
        } else {
            $rq = DB::table('leads')->where('disposition', 'Transferred to Closer')
                ->where('transferred_to', (string) $user->id);
            if ($from) $rq->where('updated_at', '>=', $from);
            if ($to) $rq->where('updated_at', '<=', $to);
            $transfersReceived = $rq->count();

            $dq = DB::table('deals')->where('closer', $user->id);
            if ($from) $dq->where('created_at', '>=', $from);
            if ($to) $dq->where('created_at', '<=', $to);
            $dealsClosed = $dq->count();

            $vq = DB::table('deals')->where('closer', $user->id)->whereNotNull('assigned_admin')
                ->whereIn('status', ['in_verification', 'charged', 'chargeback', 'chargeback_won', 'chargeback_lost']);
            if ($from) $vq->where('created_at', '>=', $from);
            if ($to) $vq->where('created_at', '<=', $to);
            $sentToVerification = $vq->count();
        }

        $notClosed = max(0, $transfersReceived - $dealsClosed);
        return [
            'transfers_received' => $transfersReceived,
            'deals_closed' => $dealsClosed,
            'sent_to_verification' => $sentToVerification,
            'not_closed' => $notClosed,
            'close_pct' => self::safePct($dealsClosed, $transfersReceived),
            'no_close_pct' => self::safePct($notClosed, $transfersReceived),
            'verification_pct' => self::safePct($sentToVerification, $dealsClosed),
        ];
    }

    /**
     * Admin's personal dashboard stats.
     */
    public static function getAdminDashboardStatsForUser(User $user, $from = null, $to = null): array
    {
        if (self::tableReady()) {
            $received = PipelineEvent::eventType(PipelineEvent::SENT_TO_VERIFICATION)
                ->forTargetUser($user->id)->inRange($from, $to)->count();
            $chargedGreen = PipelineEvent::eventType(PipelineEvent::VERIFICATION_CHARGED_GREEN)
                ->forPerformer($user->id)->inRange($from, $to)->count();
            $notCharged = PipelineEvent::eventType(PipelineEvent::VERIFICATION_NOT_CHARGED)
                ->forPerformer($user->id)->inRange($from, $to)->count();
        } else {
            $rq = DB::table('deals')->where('assigned_admin', $user->id)
                ->whereIn('status', ['in_verification', 'charged', 'chargeback', 'chargeback_won', 'chargeback_lost', 'cancelled']);
            if ($from) $rq->where('created_at', '>=', $from);
            if ($to) $rq->where('created_at', '<=', $to);
            $received = $rq->count();

            $cq = DB::table('deals')->where('assigned_admin', $user->id)->where('charged', 'yes');
            if ($from) $cq->where('charged_date', '>=', $from);
            if ($to) $cq->where('charged_date', '<=', $to);
            $chargedGreen = $cq->count();

            $nq = DB::table('deals')->where('assigned_admin', $user->id)
                ->where('charged', '!=', 'yes')->where('status', 'cancelled');
            if ($from) $nq->where('updated_at', '>=', $from);
            if ($to) $nq->where('updated_at', '<=', $to);
            $notCharged = $nq->count();
        }

        return [
            'received' => $received,
            'charged_green' => $chargedGreen,
            'not_charged' => $notCharged,
            'charge_pct' => self::safePct($chargedGreen, $received),
            'not_charged_pct' => self::safePct($notCharged, $received),
        ];
    }

    private static function overallSummaryFallback($from, $to): array
    {
        $tq = DB::table('leads')->where('disposition', 'Transferred to Closer');
        if ($from) $tq->where('updated_at', '>=', $from);
        if ($to) $tq->where('updated_at', '<=', $to);
        $totalTransfers = $tq->count();

        $dq = DB::table('deals');
        if ($from) $dq->where('created_at', '>=', $from);
        if ($to) $dq->where('created_at', '<=', $to);
        $totalDealsClosed = $dq->count();

        $vq = DB::table('deals')->whereNotNull('assigned_admin')
            ->whereIn('status', ['in_verification', 'charged', 'chargeback', 'chargeback_won', 'chargeback_lost']);
        if ($from) $vq->where('created_at', '>=', $from);
        if ($to) $vq->where('created_at', '<=', $to);
        $totalSentToVerif = $vq->count();

        $cq = DB::table('deals')->where('charged', 'yes');
        if ($from) $cq->where('charged_date', '>=', $from);
        if ($to) $cq->where('charged_date', '<=', $to);
        $totalCharged = $cq->count();

        return [
            'total_transfers' => $totalTransfers,
            'total_deals_closed' => $totalDealsClosed,
            'total_sent_to_verification' => $totalSentToVerif,
            'total_charged_green' => $totalCharged,
            'total_not_charged' => 0,
            'transfer_to_deal_pct' => self::safePct($totalDealsClosed, $totalTransfers),
            'deal_to_verification_pct' => self::safePct($totalSentToVerif, $totalDealsClosed),
            'verification_charge_pct' => self::safePct($totalCharged, $totalSentToVerif),
            'overall_conversion_pct' => self::safePct($totalCharged, $totalTransfers),
        ];
    }
}
