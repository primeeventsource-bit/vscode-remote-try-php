<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceAuditLog extends Model
{
    use HasFactory;

    protected $table = 'finance_audit_logs';

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'action',
        'user_id',
        'old_values',
        'new_values',
        'notes',
        'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function auditable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a finance audit event.
     */
    public static function record(Model $model, string $action, ?int $userId = null, array $options = []): self
    {
        return static::create([
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'action' => $action,
            'user_id' => $userId ?? auth()->id(),
            'old_values' => $options['old_values'] ?? null,
            'new_values' => $options['new_values'] ?? null,
            'notes' => $options['notes'] ?? null,
            'ip_address' => request()->ip(),
        ]);
    }
}
