<?php

namespace App\Policies;

use App\Models\ChargebackCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChargebackCasePolicy
{
    private function getEditorUsername(): string
    {
        try {
            $raw = DB::table('crm_settings')->where('key', 'chargeback.case_creator_username')->value('value');
            if ($raw) return strtolower(trim(json_decode($raw, true) ?? 'christiandior'));
        } catch (\Throwable $e) {}
        return 'christiandior';
    }

    private function isEditor(User $user): bool
    {
        return $user->hasRole('master_admin')
            && strtolower($user->username) === $this->getEditorUsername();
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
