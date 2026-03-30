<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    use HasFactory;

    protected $table = 'payroll_runs';

    const UPDATED_AT = null;

    protected $fillable = [
        'week_start',
        'week_end',
        'status',
        'created_by',
        'finalized_at',
        'notes',
    ];

    protected $casts = [
        'week_start' => 'date',
        'week_end' => 'date',
        'finalized_at' => 'datetime',
    ];

    public function entries()
    {
        return $this->hasMany(PayrollEntry::class, 'run_id');
    }
}
