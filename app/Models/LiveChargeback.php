<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LiveChargeback extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'live_chargebacks';

    protected $fillable = [
        'merchant_account_id',
        'processor_id',
        'reference_number',
        'card_brand',
        'reason_code',
        'case_number',
        'amount',
        'status',
        'event_type',
        'notes',
        'linked_reversal_id',
        'created_by',
        'updated_by',
        'dispute_date',
        'deadline_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'dispute_date' => 'date',
        'deadline_date' => 'date',
    ];

    public const STATUSES = [
        'open', 'represented', 'won', 'lost',
        'reversed', 'settled', 'monitoring',
    ];

    public function merchantAccount()
    {
        return $this->belongsTo(MerchantAccount::class);
    }

    public function processor()
    {
        return $this->belongsTo(Processor::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function linkedReversal()
    {
        return $this->belongsTo(self::class, 'linked_reversal_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'represented', 'monitoring']);
    }

    public function scopeResolved($query)
    {
        return $query->whereIn('status', ['won', 'lost', 'reversed', 'settled']);
    }

    public function getIsLossAttribute(): bool
    {
        return $this->status === 'lost';
    }

    public function getIsRecoveredAttribute(): bool
    {
        return in_array($this->status, ['won', 'reversed']);
    }
}
