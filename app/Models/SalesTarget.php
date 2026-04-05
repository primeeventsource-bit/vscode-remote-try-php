<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesTarget extends Model
{
    protected $fillable = ['role', 'user_id', 'calls_target', 'contacts_target', 'transfers_target', 'deals_target', 'revenue_target', 'effective_date', 'is_active', 'created_by'];
    protected $casts = ['revenue_target' => 'decimal:2', 'effective_date' => 'date', 'is_active' => 'boolean'];

    public function user() { return $this->belongsTo(User::class); }

    /**
     * Resolve the applicable target for a user (user > role > default).
     */
    public static function resolveForUser(User $user): ?self
    {
        // User-specific target first
        $target = self::where('user_id', $user->id)->where('is_active', true)->first();
        if ($target) return $target;

        // Role-based target
        $target = self::where('role', $user->role)->whereNull('user_id')->where('is_active', true)->first();
        if ($target) return $target;

        // System default (no role, no user)
        return self::whereNull('role')->whereNull('user_id')->where('is_active', true)->first();
    }
}
