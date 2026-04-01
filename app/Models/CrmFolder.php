<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmFolder extends Model
{
    protected $table = 'crm_folders';
    protected $fillable = ['name', 'module_type', 'created_by'];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
