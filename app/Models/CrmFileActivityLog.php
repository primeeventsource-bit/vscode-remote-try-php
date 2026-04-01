<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmFileActivityLog extends Model
{
    public $timestamps = false;
    protected $table = 'crm_file_activity_logs';
    protected $fillable = ['module_type', 'record_id', 'user_id', 'action', 'metadata', 'created_at'];
    protected $casts = ['metadata' => 'array'];

    public function user() { return $this->belongsTo(User::class, 'user_id'); }

    public static function log(string $module, int $recordId, string $action, array $meta = []): void
    {
        try {
            static::create([
                'module_type' => $module,
                'record_id' => $recordId,
                'user_id' => auth()->id(),
                'action' => $action,
                'metadata' => $meta ?: null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Non-critical
        }
    }
}
