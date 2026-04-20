<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatementChargeback extends Model
{
    use HasFactory;

    protected $table = 'statement_chargebacks';

    protected $fillable = [
        'statement_id',
        'chargeback_day',
        'chargeback_date',
        'reference_number',
        'tran_code',
        'card_brand',
        'reason_code',
        'case_number',
        'amount',
        'event_type',
        'recovered_flag',
        'linked_chargeback_id',
        'linked_reversal_id',
        'matching_confidence',
        'raw_row_text',
    ];

    protected $casts = [
        'chargeback_date' => 'date',
        'amount' => 'decimal:2',
        'recovered_flag' => 'boolean',
    ];

    public function statement()
    {
        return $this->belongsTo(MerchantStatement::class, 'statement_id');
    }

    public function linkedChargeback()
    {
        return $this->belongsTo(self::class, 'linked_chargeback_id');
    }

    public function linkedReversal()
    {
        return $this->belongsTo(self::class, 'linked_reversal_id');
    }

    public function reversals()
    {
        return $this->hasMany(self::class, 'linked_chargeback_id');
    }

    public function scopeChargebacksOnly($query)
    {
        return $query->where('event_type', 'chargeback');
    }

    public function scopeReversalsOnly($query)
    {
        return $query->where('event_type', 'reversal');
    }

    public function getIsReversalAttribute(): bool
    {
        return in_array($this->event_type, ['reversal', 'representment_credit']);
    }
}
