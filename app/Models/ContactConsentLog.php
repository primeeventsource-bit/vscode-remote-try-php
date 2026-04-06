<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContactConsentLog extends Model
{
    protected $fillable = [
        'contactable_type', 'contactable_id', 'phone_number',
        'normalized_phone', 'consent_status', 'source',
        'source_details', 'captured_by_user_id',
        'captured_at', 'revoked_at', 'notes',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'revoked_at'  => 'datetime',
    ];

    public function contactable(): MorphTo { return $this->morphTo(); }
    public function capturedBy() { return $this->belongsTo(User::class, 'captured_by_user_id'); }

    public static function isOptedOut(string $normalizedPhone): bool
    {
        return static::where('normalized_phone', $normalizedPhone)
            ->where('consent_status', 'opted_out')
            ->whereNull('revoked_at')
            ->exists();
    }
}
