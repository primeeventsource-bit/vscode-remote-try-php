<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chargeback extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_id',
        'customer_id',
        'deal_id',
        'merchant_account_id',
        'processor_id',
        'sales_rep_id',
        'product_id',
        'dispute_reference_number',
        'chargeback_amount',
        'original_transaction_amount',
        'currency',
        'status',
        'reason_code',
        'reason_description',
        'card_brand',
        'payment_method',
        'dispute_date',
        'deadline_date',
        'response_submitted_at',
        'resolved_at',
        'outcome',
        'refunded_before_dispute',
        'prevention_source',
        'source_system',
        'notes',
    ];

    protected $casts = [
        'chargeback_amount' => 'decimal:2',
        'original_transaction_amount' => 'decimal:2',
        'dispute_date' => 'date',
        'deadline_date' => 'date',
        'response_submitted_at' => 'datetime',
        'resolved_at' => 'datetime',
        'refunded_before_dispute' => 'boolean',
    ];

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function processor()
    {
        return $this->belongsTo(Processor::class);
    }

    public function merchantAccount()
    {
        return $this->belongsTo(MerchantAccount::class);
    }

    public function salesRep()
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function events()
    {
        return $this->hasMany(ChargebackEvent::class);
    }

    public function documents()
    {
        return $this->hasMany(ChargebackDocument::class);
    }
}
