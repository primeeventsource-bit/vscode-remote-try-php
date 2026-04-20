<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatementPlanSummary extends Model
{
    use HasFactory;

    protected $table = 'statement_plan_summaries';

    protected $fillable = [
        'statement_id',
        'card_brand',
        'plan_code',
        'sales_count',
        'sales_amount',
        'credit_count',
        'credit_amount',
        'net_sales',
        'average_ticket',
        'discount_rate',
        'discount_due',
    ];

    protected $casts = [
        'sales_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'net_sales' => 'decimal:2',
        'average_ticket' => 'decimal:2',
        'discount_rate' => 'decimal:4',
        'discount_due' => 'decimal:2',
    ];

    public function statement()
    {
        return $this->belongsTo(MerchantStatement::class, 'statement_id');
    }

    /**
     * Map plan code abbreviations to full brand names.
     */
    public function getBrandLabelAttribute(): string
    {
        return match (strtoupper($this->card_brand)) {
            'VS', 'VISA' => 'Visa',
            'MC', 'MASTERCARD' => 'Mastercard',
            'AM', 'AMEX', 'AMERICAN EXPRESS' => 'Amex',
            'DS', 'DISCOVER' => 'Discover',
            'DB', 'DEBIT' => 'Debit',
            'JCB' => 'JCB',
            'DN', 'DINERS' => 'Diners Club',
            default => $this->card_brand,
        };
    }
}
