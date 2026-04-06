<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemHealthCheck extends Model
{
    public $timestamps = false;

    protected $fillable = ['component', 'status', 'details', 'response_time_ms', 'checked_at'];

    protected $casts = ['details' => 'array', 'checked_at' => 'datetime'];
}
