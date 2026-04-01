<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'role',
        'avatar',
        'color',
        'status',
        'username',
        'password',
        'permissions',
        'avatar_path',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function hasPerm(string $perm): bool
    {
        $perms = is_array($this->permissions) ? $this->permissions : json_decode($this->permissions ?? '[]', true);
        return in_array('master_override', $perms) || in_array($perm, $perms);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles);
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function fronterDeals()
    {
        return $this->hasMany(Deal::class, 'fronter');
    }

    public function closerDeals()
    {
        return $this->hasMany(Deal::class, 'closer');
    }

    public function payrollEntries()
    {
        return $this->hasMany(PayrollEntry::class, 'user_id');
    }
}
