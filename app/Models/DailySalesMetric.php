<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySalesMetric extends Model
{
    protected $fillable = ['user_id', 'metric_date', 'calls_count', 'contacts_count', 'transfers_count', 'deals_closed_count', 'revenue_total', 'objection_count'];
    protected $casts = ['metric_date' => 'date', 'revenue_total' => 'decimal:2'];

    public function user() { return $this->belongsTo(User::class); }

    public static function todayForUser(User $user): self
    {
        return self::firstOrCreate(
            ['user_id' => $user->id, 'metric_date' => now()->toDateString()],
            ['calls_count' => 0, 'contacts_count' => 0, 'transfers_count' => 0, 'deals_closed_count' => 0, 'revenue_total' => 0, 'objection_count' => 0]
        );
    }
}
