<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class WeeklyStatsSnapshot extends Model
{
    protected $fillable = [
        'week_key',
        'week_start',
        'week_end',
        'total_deals',
        'total_revenue',
        'total_commissions',
        'closed_deals',
        'pending_deals',
        'cancelled_deals',
        'chargeback_deals',
        'chargeback_revenue',
        'unique_closers',
        'unique_fronters',
        'closer_breakdown',
        'fronter_breakdown',
        'daily_breakdown',
        'hourly_breakdown',
        'comparison_vs_prev',
        'raw_metrics',
    ];

    protected $casts = [
        'week_start'         => 'date',
        'week_end'           => 'date',
        'total_revenue'      => 'decimal:2',
        'total_commissions'  => 'decimal:2',
        'chargeback_revenue' => 'decimal:2',
        'closer_breakdown'   => 'array',
        'fronter_breakdown'  => 'array',
        'daily_breakdown'    => 'array',
        'hourly_breakdown'   => 'array',
        'comparison_vs_prev' => 'array',
        'raw_metrics'        => 'array',
    ];

    public static function weekKeyFor(Carbon $date): string
    {
        return $date->format('o-\WW');
    }

    public static function mondayOf(string $weekKey): Carbon
    {
        [$year, $week] = explode('-W', $weekKey);
        return Carbon::now()
            ->setISODate((int) $year, (int) $week)
            ->startOfDay();
    }

    public static function prevWeekKey(string $weekKey): string
    {
        return self::weekKeyFor(self::mondayOf($weekKey)->subWeek());
    }

    public static function nextWeekKey(string $weekKey): string
    {
        return self::weekKeyFor(self::mondayOf($weekKey)->addWeek());
    }

    public function hasData(): bool
    {
        return $this->total_deals > 0;
    }
}
