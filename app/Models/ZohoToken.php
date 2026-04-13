<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZohoToken extends Model
{
    use HasFactory;

    protected $table = 'zoho_tokens';

    protected $fillable = [
        'access_token',
        'refresh_token',
        'expires_at',
        'grant_type',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Get the most recent token record.
     */
    public static function getLatest(): ?self
    {
        return static::latest()->first();
    }

    /**
     * Check if the current token is still valid (not expired).
     */
    public static function isValid(): bool
    {
        $token = static::getLatest();

        if (!$token || !$token->expires_at) {
            return false;
        }

        return $token->expires_at->gt(Carbon::now());
    }
}
