<?php

namespace App\Policies;

use App\Models\CrmNote;
use App\Models\User;

/**
 * Notes authorization policy.
 *
 * HARD RULE: Only the ChristianDior master admin can create or edit notes.
 * This is enforced by checking: role=master_admin AND username=christiandior.
 * All other users can only view notes (if their role allows viewing the record).
 */
class CrmNotePolicy
{
    private const AUTHORIZED_EDITOR_USERNAME = 'christiandior';

    /**
     * Is this user the authorized note editor?
     */
    private function isAuthorizedEditor(User $user): bool
    {
        return $user->hasRole('master_admin')
            && strtolower($user->username) === self::AUTHORIZED_EDITOR_USERNAME;
    }

    /** Anyone with access to the record can view notes */
    public function view(User $user, CrmNote $note): bool
    {
        return true;
    }

    /** Only ChristianDior master admin */
    public function create(User $user): bool
    {
        return $this->isAuthorizedEditor($user);
    }

    /** Only ChristianDior master admin */
    public function update(User $user, CrmNote $note): bool
    {
        return $this->isAuthorizedEditor($user);
    }

    /** Admin or master admin can send notes to chat */
    public function sendToChat(User $user, CrmNote $note): bool
    {
        return $user->hasRole('master_admin', 'admin');
    }
}
