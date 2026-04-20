<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\WeeklyRepStat;
use App\Models\WeeklyStatsSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Weekly stats aggregation + snapshotting for the dashboard.
 *
 * Deal schema mapping (differs from generic spec):
 *   deal date        : timestamp (Carbon cast to date)
 *   revenue          : fee
 *   closer user id   : closer
 *   fronter user id  : fronter
 *   "closed" deal    : charged = 'yes' AND (charged_back != 'yes' OR NULL)
 *   "cancelled" deal : status = 'cancelled'
 *   "chargeback"     : charged_back = 'yes'
 *   commission       : closer_comm_amount + fronter_comm_amount
 */
class WeeklyStatsService
{
    // ─────────────────────────────────────────
    // SNAPSHOT
    // ─────────────────────────────────────────
    public function snapshotWeek(?string $weekKey = null): WeeklyStatsSnapshot
    {
        $weekKey   = $weekKey ?? WeeklyStatsSnapshot::weekKeyFor(now()->subWeek());
        $weekStart = WeeklyStatsSnapshot::mondayOf($weekKey);
        $weekEnd   = $weekStart->copy()->endOfWeek()->endOfDay();

        $deals = Deal::with(['closerUser', 'fronterUser'])
            ->whereBetween('timestamp', [$weekStart, $weekEnd])
            ->get();

        $closerBreakdown  = $this->buildCloserBreakdown($deals, $weekStart, $weekEnd);
        $fronterBreakdown = $this->buildFronterBreakdown($deals);
        $daily            = $this->dailyBreakdown($deals, $weekStart, $weekEnd);
        $hourly           = $this->hourlyBreakdown($deals);
        $comparison       = $this->buildComparison($weekKey, $deals);

        $snapshot = WeeklyStatsSnapshot::updateOrCreate(
            ['week_key' => $weekKey],
            [
                'week_start'         => $weekStart->toDateString(),
                'week_end'           => $weekEnd->toDateString(),
                'total_deals'        => $deals->count(),
                'total_revenue'      => $this->sumRevenue($deals),
                'total_commissions'  => $this->sumCommission($deals),
                'closed_deals'       => $this->filterClosed($deals)->count(),
                'pending_deals'      => $this->filterPending($deals)->count(),
                'cancelled_deals'    => $deals->where('status', 'cancelled')->count(),
                'chargeback_deals'   => $deals->where('charged_back', 'yes')->count(),
                'chargeback_revenue' => round((float) $deals->where('charged_back', 'yes')->sum('fee'), 2),
                'unique_closers'     => $deals->whereNotNull('closer')->pluck('closer')->unique()->count(),
                'unique_fronters'    => $deals->whereNotNull('fronter')->pluck('fronter')->unique()->count(),
                'closer_breakdown'   => $closerBreakdown,
                'fronter_breakdown'  => $fronterBreakdown,
                'daily_breakdown'    => $daily,
                'hourly_breakdown'   => $hourly,
                'comparison_vs_prev' => $comparison,
            ]
        );

        $this->saveRepStats($weekKey, $weekStart, $closerBreakdown, $fronterBreakdown);

        return $snapshot;
    }

    // ─────────────────────────────────────────
    // GET STATS — live or historical
    // ─────────────────────────────────────────
    public function getWeekStats(?string $weekKey = null): array
    {
        $currentKey = WeeklyStatsSnapshot::weekKeyFor(now());
        $weekKey    = $weekKey ?? $currentKey;
        $isLive     = ($weekKey === $currentKey);

        if ($isLive) {
            return $this->getLiveStats($weekKey);
        }

        $snap = WeeklyStatsSnapshot::where('week_key', $weekKey)->first();
        if (! $snap) {
            return $this->getEmptyStats($weekKey);
        }

        $arr = $snap->toArray();
        $arr['is_live']  = false;
        $arr['week_key'] = $weekKey;

        return $arr;
    }

    private function getLiveStats(string $weekKey): array
    {
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd   = Carbon::now()->endOfWeek()->endOfDay();

        $deals = Deal::with(['closerUser', 'fronterUser'])
            ->whereBetween('timestamp', [$weekStart, $weekEnd])
            ->get();

        $closerBreakdown  = $this->buildCloserBreakdown($deals, $weekStart, $weekEnd);
        $fronterBreakdown = $this->buildFronterBreakdown($deals);

        return [
            'is_live'            => true,
            'week_key'           => $weekKey,
            'week_start'         => $weekStart->toDateString(),
            'week_end'           => $weekEnd->toDateString(),
            'total_deals'        => $deals->count(),
            'total_revenue'      => $this->sumRevenue($deals),
            'total_commissions'  => $this->sumCommission($deals),
            'closed_deals'       => $this->filterClosed($deals)->count(),
            'pending_deals'      => $this->filterPending($deals)->count(),
            'cancelled_deals'    => $deals->where('status', 'cancelled')->count(),
            'chargeback_deals'   => $deals->where('charged_back', 'yes')->count(),
            'chargeback_revenue' => round((float) $deals->where('charged_back', 'yes')->sum('fee'), 2),
            'unique_closers'     => count($closerBreakdown),
            'unique_fronters'    => count($fronterBreakdown),
            'closer_breakdown'   => $closerBreakdown,
            'fronter_breakdown'  => $fronterBreakdown,
            'daily_breakdown'    => $this->dailyBreakdown($deals, $weekStart, $weekEnd),
            'hourly_breakdown'   => $this->hourlyBreakdown($deals),
            'comparison_vs_prev' => $this->buildComparison($weekKey, $deals),
        ];
    }

    private function getEmptyStats(string $weekKey): array
    {
        $start = WeeklyStatsSnapshot::mondayOf($weekKey);
        return [
            'is_live'            => false,
            'week_key'           => $weekKey,
            'week_start'         => $start->toDateString(),
            'week_end'           => $start->copy()->endOfWeek()->toDateString(),
            'total_deals'        => 0,
            'total_revenue'      => 0,
            'total_commissions'  => 0,
            'closed_deals'       => 0,
            'pending_deals'      => 0,
            'cancelled_deals'    => 0,
            'chargeback_deals'   => 0,
            'chargeback_revenue' => 0,
            'unique_closers'     => 0,
            'unique_fronters'    => 0,
            'closer_breakdown'   => [],
            'fronter_breakdown'  => [],
            'daily_breakdown'    => [],
            'hourly_breakdown'   => [],
            'comparison_vs_prev' => null,
        ];
    }

    public function availableWeeks(): Collection
    {
        return WeeklyStatsSnapshot::orderByDesc('week_start')->pluck('week_key');
    }

    public function backfillAllWeeks(?callable $onProgress = null): int
    {
        $oldest  = Deal::oldest('timestamp')->first()?->timestamp ?? now();
        $oldest  = $oldest instanceof Carbon ? $oldest : Carbon::parse($oldest);
        $current = $oldest->copy()->startOfWeek();
        $thisWeekStart = now()->startOfWeek();
        $count = 0;

        while ($current->lt($thisWeekStart)) {
            $key = WeeklyStatsSnapshot::weekKeyFor($current);
            $this->snapshotWeek($key);
            $count++;
            if ($onProgress) {
                $onProgress($key);
            }
            $current->addWeek();
        }

        return $count;
    }

    // ─────────────────────────────────────────
    // INTERNAL HELPERS
    // ─────────────────────────────────────────

    private function officeFor(?string $role): string
    {
        return str_contains((string) $role, 'panama') ? 'Panama' : 'US';
    }

    private function sumRevenue(Collection $deals): float
    {
        // Revenue = fee of successfully charged non-chargedback deals
        return round((float) $this->filterClosed($deals)->sum('fee'), 2);
    }

    private function sumCommission(Collection $deals): float
    {
        return round(
            (float) $this->filterClosed($deals)->sum(fn($d) =>
                (float) ($d->closer_comm_amount ?? 0) + (float) ($d->fronter_comm_amount ?? 0)
            ),
            2
        );
    }

    private function filterClosed(Collection $deals): Collection
    {
        return $deals->filter(fn($d) =>
            ($d->charged ?? null) === 'yes'
            && ($d->charged_back ?? null) !== 'yes'
        );
    }

    private function filterPending(Collection $deals): Collection
    {
        return $deals->filter(fn($d) =>
            ($d->charged ?? null) !== 'yes'
            && ($d->status ?? null) !== 'cancelled'
        );
    }

    private function buildCloserBreakdown(Collection $deals, Carbon $weekStart, Carbon $weekEnd): array
    {
        return $deals
            ->filter(fn($d) => ! empty($d->closer))
            ->groupBy('closer')
            ->map(function ($group, $userId) use ($weekStart, $weekEnd) {
                /** @var Deal $first */
                $first  = $group->first();
                $user   = $first->closerUser;
                $closed = $this->filterClosed($group);
                $daily  = $this->dailyBreakdown($group, $weekStart, $weekEnd);

                return [
                    'user_id'     => (int) $userId,
                    'name'        => $user?->name ?? 'Unknown',
                    'role'        => $user?->role ?? 'closer',
                    'office'      => $this->officeFor($user?->role),
                    'deals'       => $group->count(),
                    'closed'      => $closed->count(),
                    'cancelled'   => $group->where('status', 'cancelled')->count(),
                    'pending'     => $this->filterPending($group)->count(),
                    'revenue'     => round((float) $closed->sum('fee'), 2),
                    'commission'  => round(
                        (float) $closed->sum(fn($d) =>
                            (float) ($d->closer_comm_amount ?? 0) + (float) ($d->fronter_comm_amount ?? 0)
                        ),
                        2
                    ),
                    'avg_sale'    => $closed->count() > 0
                        ? round((float) $closed->avg('fee'), 2)
                        : 0,
                    'top_day'     => collect($daily)->sortByDesc('revenue')->first()['date'] ?? null,
                    'daily_stats' => $daily,
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->toArray();
    }

    private function buildFronterBreakdown(Collection $deals): array
    {
        return $deals
            ->filter(fn($d) => ! empty($d->fronter))
            ->groupBy('fronter')
            ->map(function ($group, $userId) {
                /** @var Deal $first */
                $first   = $group->first();
                $user    = $first->fronterUser;
                $total   = $group->count();
                $closed  = $this->filterClosed($group)->count();

                return [
                    'user_id'          => (int) $userId,
                    'name'             => $user?->name ?? 'Unknown',
                    'role'             => $user?->role ?? 'fronter',
                    'office'           => $this->officeFor($user?->role),
                    'sets'             => $total,
                    'sets_closed'      => $closed,
                    'cancelled'        => $group->where('status', 'cancelled')->count(),
                    'conversion_rate'  => $total > 0 ? round(($closed / $total) * 100, 1) : 0,
                    'revenue_assisted' => round((float) $this->filterClosed($group)->sum('fee'), 2),
                ];
            })
            ->sortByDesc('sets')
            ->values()
            ->toArray();
    }

    private function dailyBreakdown(Collection $deals, Carbon $start, Carbon $end): array
    {
        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $day = $deals->filter(function ($deal) use ($d) {
                $t = $deal->timestamp;
                if (! $t) return false;
                $date = $t instanceof Carbon ? $t : Carbon::parse($t);
                return $date->isSameDay($d);
            });
            $closed = $this->filterClosed($day);
            $days[] = [
                'date'      => $d->toDateString(),
                'day_name'  => $d->format('D'),
                'deals'     => $day->count(),
                'revenue'   => round((float) $closed->sum('fee'), 2),
                'closed'    => $closed->count(),
                'cancelled' => $day->where('status', 'cancelled')->count(),
            ];
        }
        return $days;
    }

    private function hourlyBreakdown(Collection $deals): array
    {
        $buckets = array_fill(0, 24, 0);
        foreach ($deals as $deal) {
            $created = $deal->created_at ?? $deal->timestamp;
            if (! $created) continue;
            $hour = ($created instanceof Carbon ? $created : Carbon::parse($created))->hour;
            $buckets[$hour]++;
        }
        $out = [];
        foreach ($buckets as $h => $c) {
            $out[] = ['hour' => $h, 'deals' => $c];
        }
        return $out;
    }

    private function buildComparison(string $weekKey, Collection $deals): ?array
    {
        $prevKey  = WeeklyStatsSnapshot::prevWeekKey($weekKey);
        $prevSnap = WeeklyStatsSnapshot::where('week_key', $prevKey)->first();
        if (! $prevSnap) {
            return null;
        }

        $thisDealCount   = $deals->count();
        $thisRevenue     = $this->sumRevenue($deals);
        $prevDealCount   = (int) $prevSnap->total_deals;
        $prevRevenue     = (float) $prevSnap->total_revenue;

        $dealsDelta   = $thisDealCount - $prevDealCount;
        $revenueDelta = $thisRevenue - $prevRevenue;

        return [
            'prev_week_key' => $prevKey,
            'deals_delta'   => $dealsDelta,
            'revenue_delta' => round($revenueDelta, 2),
            'deals_pct'     => $prevDealCount > 0 ? round(($dealsDelta / $prevDealCount) * 100, 1) : null,
            'revenue_pct'   => $prevRevenue > 0 ? round(($revenueDelta / $prevRevenue) * 100, 1) : null,
            'prev_deals'    => $prevDealCount,
            'prev_revenue'  => $prevRevenue,
            'prev_daily'    => $prevSnap->daily_breakdown ?? [],
        ];
    }

    private function saveRepStats(string $weekKey, Carbon $weekStart, array $closers, array $fronters): void
    {
        foreach ($closers as $i => $c) {
            WeeklyRepStat::updateOrCreate(
                ['week_key' => $weekKey, 'user_id' => $c['user_id']],
                [
                    'week_start'       => $weekStart->toDateString(),
                    'role'             => $c['role'],
                    'office'           => $c['office'],
                    'deals_total'      => $c['deals'],
                    'deals_closed'     => $c['closed'],
                    'deals_cancelled'  => $c['cancelled'],
                    'deals_pending'    => $c['pending'],
                    'revenue_total'    => $c['revenue'],
                    'commission_total' => $c['commission'],
                    'avg_sale_amount'  => $c['avg_sale'],
                    'rank_revenue'     => $i + 1,
                    'daily_stats'      => $c['daily_stats'],
                ]
            );
        }

        foreach ($fronters as $i => $f) {
            WeeklyRepStat::updateOrCreate(
                ['week_key' => $weekKey, 'user_id' => $f['user_id']],
                [
                    'week_start'      => $weekStart->toDateString(),
                    'role'            => $f['role'],
                    'office'          => $f['office'],
                    'sets_total'      => $f['sets'],
                    'sets_closed'     => $f['sets_closed'],
                    'conversion_rate' => $f['conversion_rate'],
                    'rank_deals'      => $i + 1,
                ]
            );
        }
    }
}
