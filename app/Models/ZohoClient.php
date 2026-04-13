<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZohoClient extends Model
{
    use HasFactory;

    protected $table = 'zoho_clients';

    protected $fillable = [
        'zoho_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'mobile',
        'account_name',
        'title',
        'department',
        'mailing_address',
        'mailing_city',
        'mailing_state',
        'mailing_zip',
        'mailing_country',
        'lead_source',
        'contact_owner',
        'status',
        'last_synced_at',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get the client's full name.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Deals associated with this client.
     */
    public function deals(): HasMany
    {
        return $this->hasMany(ZohoDeal::class);
    }

    /**
     * Activities associated with this client.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(ZohoActivity::class);
    }

    /**
     * Zoho notes associated with this client.
     */
    public function zohoNotes(): HasMany
    {
        return $this->hasMany(ZohoNote::class);
    }

    /**
     * Internal client notes associated with this client.
     */
    public function clientNotes(): HasMany
    {
        return $this->hasMany(ZohoClientNote::class);
    }

    /**
     * Scope to filter active clients.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to search clients by name, email, phone, or account name.
     */
    public function scopeSearch($query, string $term)
    {
        $term = '%' . $term . '%';

        return $query->where(function ($q) use ($term) {
            $q->where('first_name', 'like', $term)
              ->orWhere('last_name', 'like', $term)
              ->orWhere('email', 'like', $term)
              ->orWhere('phone', 'like', $term)
              ->orWhere('account_name', 'like', $term);
        });
    }
}
