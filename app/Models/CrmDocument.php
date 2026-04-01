<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmDocument extends Model
{
    protected $table = 'crm_documents';

    protected $fillable = [
        'title', 'content', 'type', 'owner_id', 'folder_id', 'status',
        'is_uploaded', 'original_filename', 'stored_path', 'mime_type', 'file_size',
    ];

    protected $casts = ['is_uploaded' => 'boolean'];

    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function folder() { return $this->belongsTo(CrmFolder::class, 'folder_id'); }
    public function permissions() { return $this->hasMany(CrmDocumentPermission::class, 'document_id'); }

    public function scopeAccessibleBy($query, User $user)
    {
        if ($user->hasRole('master_admin')) return $query->where('status', 'active');
        return $query->where('status', 'active')->where(function ($q) use ($user) {
            $q->where('owner_id', $user->id)
              ->orWhereHas('permissions', fn($p) => $p->where('user_id', $user->id));
        });
    }

    public function userCan(User $user, string $action): bool
    {
        if ($user->hasRole('master_admin')) return true;
        if ($user->id === $this->owner_id && in_array($action, ['view', 'edit', 'delete', 'share'])) return true;
        $perm = $this->permissions()->where('user_id', $user->id)->first();
        if (!$perm) return false;
        if ($action === 'view') return true;
        if ($action === 'edit') return $perm->permission_type === 'edit';
        return false;
    }
}
