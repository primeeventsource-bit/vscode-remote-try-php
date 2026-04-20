<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyRepStat extends Model
{
    protected $fillable = [
        'week_key',
        'week_start',
        'user_id',
        'role',
        'office',
        'deals_total',
        'deals_closed',
        'deals_cancelled',
        'deals_pending',
        'revenue_total',
        'commission_total',
        'avg_sale_amount',
        'sets_total',
        'sets_closed',
        'conversion_rate',
        'rank_revenue',
        'rank_deals',
        'daily_stats',
    ];

    protected $casts = [
        'week_start'       => 'date',
        'revenue_total'    => 'decimal:2',
        'commission_total' => 'decimal:2',
        'avg_sale_amount'  => 'decimal:2',
        'conversion_rate'  => 'decimal:2',
        'daily_stats'      => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
