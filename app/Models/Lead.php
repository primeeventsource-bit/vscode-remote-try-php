<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $table = 'leads';

    protected $fillable = [
        'resort',
        'owner_name',
        'phone1',
        'phone2',
        'city',
        'st',
        'zip',
        'resort_location',
        'assigned_to',
        'original_fronter',
        'disposition',
        'transferred_to',
        'source',
        'callback_date',
    ];

    protected $casts = [
        'callback_date' => 'datetime',
    ];

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function originalFronter()
    {
        return $this->belongsTo(User::class, 'original_fronter');
    }

    public function transfers()
    {
        return $this->hasMany(LeadTransfer::class);
    }

    public function transferredToUser()
    {
        return $this->belongsTo(User::class, 'transferred_to');
    }
}
