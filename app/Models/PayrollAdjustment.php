<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollAdjustment extends Model
{
    use HasFactory;

    protected $table = 'payroll_adjustments';

    const UPDATED_AT = null;

    protected $fillable = [
        'entry_id',
        'user_id',
        'type',
        'description',
        'amount',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function entry()
    {
        return $this->belongsTo(PayrollEntry::class, 'entry_id');
    }
}
