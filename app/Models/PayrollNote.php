<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollNote extends Model
{
    use HasFactory;

    protected $table = 'payroll_notes';

    const CREATED_AT = null;

    protected $fillable = [
        'user_id',
        'week_start',
        'note',
        'created_by',
    ];

    protected $casts = [
        'week_start' => 'date',
    ];
}
