<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmSheetPermission extends Model
{
    public $timestamps = false;
    protected $table = 'crm_sheet_permissions';
    protected $fillable = ['sheet_id', 'user_id', 'permission_type', 'granted_by', 'created_at'];

    public function sheet() { return $this->belongsTo(CrmSheet::class, 'sheet_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
