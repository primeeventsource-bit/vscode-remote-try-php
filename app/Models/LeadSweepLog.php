<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadSweepLog extends Model
{
    protected $table = 'lead_sweep_log';

    public $timestamps = false;

    protected $fillable = [
        'lead_id', 'field_name', 'old_value', 'new_value', 'rule',
        'reverted_by', 'reverted_at', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'reverted_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function isReverted(): bool
    {
        return $this->reverted_at !== null;
    }
}
