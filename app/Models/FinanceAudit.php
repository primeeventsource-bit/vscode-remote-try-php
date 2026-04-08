<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceAudit extends Model
{
    protected $table = 'finance_audits';

    protected $fillable = [
        'auditable_type', 'auditable_id', 'action',
        'before_json', 'after_json', 'note', 'user_id',
    ];

    protected $casts = [
        'before_json' => 'array',
        'after_json' => 'array',
    ];

    public function user() { return $this->belongsTo(User::class); }

    public static function record(string $type, int $id, string $action, ?array $before, ?array $after, ?string $note = null): void
    {
        self::create([
            'auditable_type' => $type,
            'auditable_id' => $id,
            'action' => $action,
            'before_json' => $before,
            'after_json' => $after,
            'note' => $note,
            'user_id' => auth()->id(),
        ]);
    }
}
