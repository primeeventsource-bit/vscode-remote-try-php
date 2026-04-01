<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChargebackDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'chargeback_id',
        'file_path',
        'file_name',
        'file_type',
        'uploaded_by',
    ];

    public function chargeback()
    {
        return $this->belongsTo(Chargeback::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
