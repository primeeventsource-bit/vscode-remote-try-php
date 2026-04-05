<?php

namespace App\Policies;

use App\Models\CrmNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CrmNotePolicy
{
    private function getAuthorizedUsername(): string
    {
        try {
            $raw = DB::table('crm_settings')->where('key', 'notes.note_creator_username')->value('value');
            if ($raw) return strtolower(trim(json_decode($raw, true) ?? 'christiandior'));
        } catch (\Throwable $e) {}
        return 'christiandior';
    }

    private function isAuthorizedEditor(User $user): bool
    {
        return $user->hasRole('master_admin')
            && strtolower($user->username) === $this->getAuthorizedUsername();
    }

    public function view(User $user, CrmNote $note): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->isAuthorizedEditor($user);
    }

    public function update(User $user, CrmNote $note): bool
    {
        return $this->isAuthorizedEditor($user);
    }

    public function sendToChat(User $user, CrmNote $note): bool
    {
        return $user->hasRole('master_admin', 'admin');
    }
}
