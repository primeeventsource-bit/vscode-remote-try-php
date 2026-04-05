<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargebackEvidence extends Model
{
    protected $table = 'chargeback_evidence';

    protected $fillable = [
        'chargeback_case_id', 'document_type', 'original_filename',
        'stored_filename', 'file_path', 'mime_type', 'file_size',
        'status', 'uploaded_by_user_id', 'verified_by_user_id', 'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function chargebackCase()
    {
        return $this->belongsTo(ChargebackCase::class, 'chargeback_case_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
