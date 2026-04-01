<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'processor_id',
        'name',
        'mid_masked',
        'active',
    ];

    public function processor()
    {
        return $this->belongsTo(Processor::class);
    }

    public function chargebacks()
    {
        return $this->hasMany(Chargeback::class);
    }
}
