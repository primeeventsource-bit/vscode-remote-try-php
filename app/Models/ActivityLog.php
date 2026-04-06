<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'subject_type', 'subject_id',
        'event', 'properties', 'created_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject()
    {
        return $this->morphTo();
    }

    /**
     * Log a CRM activity event:
     *   ActivityLog::log('status_changed', $lead, ['from' => 'new', 'to' => 'qualified']);
     */
    public static function log(string $event, Model $subject, ?array $properties = null): self
    {
        return static::create([
            'user_id'      => auth()->id(),
            'subject_type' => get_class($subject),
            'subject_id'   => $subject->getKey(),
            'event'        => $event,
            'properties'   => $properties,
            'created_at'   => now(),
        ]);
    }
}
