<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmDocumentPermission extends Model
{
    public $timestamps = false;
    protected $table = 'crm_document_permissions';
    protected $fillable = ['document_id', 'user_id', 'permission_type', 'granted_by', 'created_at'];

    public function document() { return $this->belongsTo(CrmDocument::class, 'document_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
