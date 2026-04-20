<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceReviewItem extends Model
{
    use HasFactory;

    protected $table = 'finance_review_queue';

    protected $fillable = [
        'statement_id',
        'field_name',
        'section',
        'extracted_value',
        'corrected_value',
        'notes',
        'status',
        'confidence',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function statement()
    {
        return $this->belongsTo(MerchantStatement::class, 'statement_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
