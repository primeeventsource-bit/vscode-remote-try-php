<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadImportFailure extends Model
{
    protected $fillable = [
        'lead_import_batch_id',
        'row_number',
        'raw_row',
        'reason',
        'failure_type',
        'matched_lead_id',
        'duplicate_type',
        'duplicate_reason',
        'matched_fields',
        'resolution_status',
    ];

    protected $casts = [
        'raw_row' => 'array',
        'matched_fields' => 'array',
    ];

    public function batch()
    {
        return $this->belongsTo(LeadImportBatch::class, 'lead_import_batch_id');
    }

    public function matchedLead()
    {
        return $this->belongsTo(Lead::class, 'matched_lead_id')->withTrashed();
    }
}
