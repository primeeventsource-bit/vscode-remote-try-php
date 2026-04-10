<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtlasLead extends Model
{
    protected $fillable = [
        'grantee', 'grantor', 'county', 'state', 'city', 'zip',
        'deed_date', 'address', 'instrument', 'deed_type',
        'existing_phone',
        'phone_1', 'phone_1_type', 'phone_2', 'phone_2_type',
        'phone_3', 'phone_3_type', 'phone_4', 'phone_4_type',
        'phone_5', 'phone_5_type', 'phone_confidence',
        'email_1', 'email_2', 'email_3',
        'status', 'source', 'source_filename', 'notes',
        'created_by', 'assigned_to', 'traced_at',
    ];

    protected $casts = [
        'deed_date' => 'date',
        'traced_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(fn($q) => $q
            ->where('grantee', 'like', "%{$term}%")
            ->orWhere('grantor', 'like', "%{$term}%")
            ->orWhere('county', 'like', "%{$term}%")
            ->orWhere('address', 'like', "%{$term}%"));
    }

    public function getPhones(): array
    {
        return array_filter([
            $this->phone_1 ? ['number' => $this->phone_1, 'type' => $this->phone_1_type] : null,
            $this->phone_2 ? ['number' => $this->phone_2, 'type' => $this->phone_2_type] : null,
            $this->phone_3 ? ['number' => $this->phone_3, 'type' => $this->phone_3_type] : null,
            $this->phone_4 ? ['number' => $this->phone_4, 'type' => $this->phone_4_type] : null,
            $this->phone_5 ? ['number' => $this->phone_5, 'type' => $this->phone_5_type] : null,
        ]);
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'new' => '#e94560',
            'searched' => '#f5a623',
            'traced' => '#00d4ff',
            'imported' => '#0fff50',
            default => '#666',
        };
    }

    public function getSourceBadge(): array
    {
        return match ($this->source) {
            'sheets' => ['icon' => '📊', 'color' => '#0fff50'],
            'ai-text' => ['icon' => '🤖', 'color' => '#c8a44e'],
            'ai-pdf' => ['icon' => '📄', 'color' => '#f5a623'],
            default => ['icon' => '✏️', 'color' => '#666'],
        };
    }
}
