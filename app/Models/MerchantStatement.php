<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantStatement extends Model
{
    use HasFactory;

    protected $table = 'merchant_statements';

    protected $fillable = [
        'merchant_account_id',
        'processor_id',
        'statement_month',
        'gross_sales',
        'credits',
        'net_sales',
        'discount_due',
        'discount_paid',
        'fees_due',
        'fees_paid',
        'net_fees_due',
        'amount_deducted',
        'total_deposits',
        'total_chargebacks',
        'total_reversals',
        'reserve_ending_balance',
        'upload_filename',
        'upload_file_path',
        'raw_text',
        'parsed_json',
        'detected_processor',
        'detection_confidence',
        'parser_version',
        'ai_parse_status',
        'validation_status',
        'validation_notes',
        'review_status',
        'uploaded_by',
        'reviewed_by',
        'parsed_at',
        'reviewed_at',
    ];

    protected $casts = [
        'gross_sales' => 'decimal:2',
        'credits' => 'decimal:2',
        'net_sales' => 'decimal:2',
        'discount_due' => 'decimal:2',
        'discount_paid' => 'decimal:2',
        'fees_due' => 'decimal:2',
        'fees_paid' => 'decimal:2',
        'net_fees_due' => 'decimal:2',
        'amount_deducted' => 'decimal:2',
        'total_deposits' => 'decimal:2',
        'total_chargebacks' => 'decimal:2',
        'total_reversals' => 'decimal:2',
        'reserve_ending_balance' => 'decimal:2',
        'parsed_json' => 'array',
        'parsed_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────
    public function merchantAccount()
    {
        return $this->belongsTo(MerchantAccount::class);
    }

    public function processor()
    {
        return $this->belongsTo(Processor::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function planSummaries()
    {
        return $this->hasMany(StatementPlanSummary::class, 'statement_id');
    }

    public function deposits()
    {
        return $this->hasMany(StatementDeposit::class, 'statement_id');
    }

    public function chargebacks()
    {
        return $this->hasMany(StatementChargeback::class, 'statement_id');
    }

    public function reserves()
    {
        return $this->hasMany(StatementReserve::class, 'statement_id');
    }

    public function fees()
    {
        return $this->hasMany(StatementFee::class, 'statement_id');
    }

    public function reviewItems()
    {
        return $this->hasMany(FinanceReviewItem::class, 'statement_id');
    }

    public function parserLogs()
    {
        return $this->hasMany(ParserLog::class, 'statement_id');
    }

    // ── Scopes ───────────────────────────────────────────
    public function scopeCompleted($query)
    {
        return $query->where('ai_parse_status', 'completed');
    }

    public function scopeForMonth($query, string $month)
    {
        return $query->where('statement_month', $month);
    }

    public function scopeForMerchant($query, int $merchantId)
    {
        return $query->where('merchant_account_id', $merchantId);
    }

    public function scopeNeedsReview($query)
    {
        return $query->where('review_status', 'pending');
    }

    // ── Computed ─────────────────────────────────────────
    public function getNetChargebackLossAttribute(): float
    {
        return (float) $this->total_chargebacks - (float) $this->total_reversals;
    }

    public function getChargebackRatioAttribute(): float
    {
        $gross = (float) $this->gross_sales;
        return $gross > 0 ? round(((float) $this->total_chargebacks / $gross) * 100, 4) : 0;
    }

    public function getTotalProcessorFeesAttribute(): float
    {
        return (float) $this->discount_paid + (float) $this->fees_paid;
    }
}
