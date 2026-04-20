<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceManualExpense extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'finance_manual_expenses';

    protected $fillable = [
        'merchant_account_id',
        'expense_date',
        'category',
        'description',
        'amount',
        'is_recurring',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'is_recurring' => 'boolean',
    ];

    public const CATEGORIES = [
        'software', 'rent', 'advertising', 'chargeback_service',
        'phone', 'contractor', 'bank_fees', 'misc',
    ];

    public function merchantAccount()
    {
        return $this->belongsTo(MerchantAccount::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
