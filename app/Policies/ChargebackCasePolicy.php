<?php

namespace App\Policies;

use App\Models\ChargebackCase;
use App\Models\User;

class ChargebackCasePolicy
{
    private const AUTHORIZED_EDITOR = 'christiandior';

    private function isEditor(User $user): bool
    {
        return $user->hasRole('master_admin')
            && strtolower($user->username) === self::AUTHORIZED_EDITOR;
    }

    public function view(User $user, ChargebackCase $case): bool
    {
        return $user->hasRole('master_admin', 'admin');
    }

    public function create(User $user): bool
    {
        return $this->isEditor($user);
    }

    public function update(User $user, ChargebackCase $case): bool
    {
        return $this->isEditor($user);
    }

    public function upload(User $user, ChargebackCase $case): bool
    {
        return $this->isEditor($user);
    }

    public function verify(User $user, ChargebackCase $case): bool
    {
        return $this->isEditor($user);
    }

    public function export(User $user, ChargebackCase $case): bool
    {
        return $this->isEditor($user);
    }

    public function sendToChat(User $user, ChargebackCase $case): bool
    {
        return $user->hasRole('master_admin', 'admin');
    }
}
