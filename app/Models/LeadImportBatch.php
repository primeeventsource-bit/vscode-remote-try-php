<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadImportBatch extends Model
{
    protected $fillable = [
        'user_id',
        'filename',
        'original_filename',
        'file_path',
        'file_type',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'duplicate_rows',
        'invalid_rows',
        'failed_rows',
        'status',
        'duplicate_strategy',
        'started_at',
        'completed_at',
        'error_message',
        'summary_json',
    ];

    protected $casts = [
        'summary_json' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'import_batch_id');
    }

    public function failures()
    {
        return $this->hasMany(LeadImportFailure::class);
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function progressPercent(): int
    {
        if ($this->total_rows === 0) return 0;
        return (int) min(100, round(($this->processed_rows / $this->total_rows) * 100));
    }
}
