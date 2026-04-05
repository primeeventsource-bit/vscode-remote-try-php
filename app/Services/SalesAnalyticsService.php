<?php

namespace App\Services;

use App\Models\DailySalesMetric;
use App\Models\SalesTarget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesAnalyticsService
{
    private static function ready(): bool
    {
        return Schema::hasTable('daily_sales_metrics');
    }

    public static function getDailyProgressForUser(User $user): array
    {
        $target = SalesTarget::resolveForUser($user);
        $metric = self::ready() ? DailySalesMetric::todayForUser($user) : null;

        return [
            'calls' => ['actual' => $metric->calls_count ?? 0, 'target' => $target->calls_target ?? 0],
            'contacts' => ['actual' => $metric->contacts_count ?? 0, 'target' => $target->contacts_target ?? 0],
            'transfers' => ['actual' => $metric->transfers_count ?? 0, 'target' => $target->transfers_target ?? 0],
            'deals' => ['actual' => $metric->deals_closed_count ?? 0, 'target' => $target->deals_target ?? 0],
            'revenue' => ['actual' => (float) ($metric->revenue_total ?? 0), 'target' => (float) ($target->revenue_target ?? 0)],
            'objections' => $metric->objection_count ?? 0,
        ];
    }

    public static function getLeaderboard(string $period = 'today', int $limit = 10): array
    {
        if (!self::ready()) return [];

        $query = DB::table('daily_sales_metrics')
            ->join('users', 'daily_sales_metrics.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.avatar', 'users.color', 'users.role',
                DB::raw('SUM(deals_closed_count) as total_deals'),
                DB::raw('SUM(revenue_total) as total_revenue'),
                DB::raw('SUM(calls_count) as total_calls'));

        if ($period === 'today') {
            $query->where('metric_date', now()->toDateString());
        } elseif ($period === 'week') {
            $query->where('metric_date', '>=', now()->startOfWeek()->toDateString());
        } elseif ($period === 'month') {
            $query->where('metric_date', '>=', now()->startOfMonth()->toDateString());
        }

        return $query->groupBy('users.id', 'users.name', 'users.avatar', 'users.color', 'users.role')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'avatar' => $r->avatar,
                'color' => $r->color,
                'role' => $r->role,
                'deals' => (int) $r->total_deals,
                'revenue' => (float) $r->total_revenue,
                'calls' => (int) $r->total_calls,
            ])
            ->toArray();
    }

    public static function incrementMetric(User $user, string $field, int|float $amount = 1): void
    {
        if (!self::ready()) return;
        $metric = DailySalesMetric::todayForUser($user);
        $metric->increment($field, $amount);
    }
}
