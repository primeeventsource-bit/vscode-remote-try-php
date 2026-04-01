<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Processor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'provider_type',
        'active',
    ];

    public function merchantAccounts()
    {
        return $this->hasMany(MerchantAccount::class);
    }

    public function chargebacks()
    {
        return $this->hasMany(Chargeback::class);
    }
}
