<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatementReserve extends Model
{
    use HasFactory;

    protected $table = 'statement_reserves';

    protected $fillable = [
        'statement_id',
        'reserve_day',
        'reserve_date',
        'reserve_amount',
        'release_amount',
        'running_balance',
        'raw_row_text',
    ];

    protected $casts = [
        'reserve_date' => 'date',
        'reserve_amount' => 'decimal:2',
        'release_amount' => 'decimal:2',
        'running_balance' => 'decimal:2',
    ];

    public function statement()
    {
        return $this->belongsTo(MerchantStatement::class, 'statement_id');
    }
}
