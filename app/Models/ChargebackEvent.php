<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChargebackEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'chargeback_id',
        'event_type',
        'old_status',
        'new_status',
        'event_date',
        'performed_by',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'metadata' => 'array',
    ];

    public function chargeback()
    {
        return $this->belongsTo(Chargeback::class);
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
