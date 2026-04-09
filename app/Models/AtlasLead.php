<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtlasLead extends Model
{
    protected $fillable = [
        'grantee', 'grantor', 'county', 'state', 'deed_date', 'address',
        'instrument', 'deed_type', 'phone_1', 'phone_2', 'phone_3',
        'phone_confidence', 'phone_sources', 'status', 'source',
        'source_filename', 'notes', 'created_by', 'assigned_to',
    ];

    protected $casts = [
        'deed_date' => 'date',
        'phone_sources' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function getPhones(): array
    {
        return array_values(array_filter([$this->phone_1, $this->phone_2, $this->phone_3]));
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'new' => '#e94560',
            'searched' => '#f5a623',
            'traced' => '#00d4ff',
            'imported' => '#0fff50',
            default => '#888',
        };
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCounty($query, string $county)
    {
        return $query->where('county', $county);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('grantee', 'like', "%{$term}%")
              ->orWhere('grantor', 'like', "%{$term}%")
              ->orWhere('county', 'like', "%{$term}%");
        });
    }
}
