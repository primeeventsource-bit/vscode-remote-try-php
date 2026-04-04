<?php

namespace App\Policies;

use App\Models\Deal;
use App\Models\User;

/**
 * Authorization policy for client records (Deal model in charged status).
 *
 * All checks are backend-enforced. The Livewire component and Blade views
 * use these methods to decide what to render AND what to allow.
 */
class ClientPolicy
{
    /**
     * Master admin bypasses all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('master_admin') || $user->hasPerm('master_override')) {
            return true;
        }
        return null;
    }

    /** View client list and basic info */
    public function view(User $user, Deal $deal): bool
    {
        return $user->hasPerm('clients.view')
            || $user->hasRole('admin')
            || $user->hasPerm('view_deals');
    }

    /** Edit basic client info (name, email, phone, address, notes, status) */
    public function edit(User $user, Deal $deal): bool
    {
        return $user->hasPerm('clients.edit')
            || $user->hasRole('admin');
    }

    /** View deal sheet section */
    public function viewDealSheet(User $user, Deal $deal): bool
    {
        return $user->hasPerm('clients.view_deal_sheet')
            || $user->hasRole('admin')
            || $user->hasPerm('view_deals');
    }

    /** Edit deal sheet fields */
    public function editDealSheet(User $user, Deal $deal): bool
    {
        return $user->hasPerm('clients.edit_deal_sheet')
            || $user->hasRole('admin');
    }

    /** View banking info (bank name, bank address) */
    public function viewBanking(User $user, Deal $deal): bool
    {
        return $user->hasPerm('clients.view_banking')
            || $user->hasRole('admin');
    }

    /** Edit banking info */
    public function editBanking(User $user, Deal $deal): bool
    {
        return $user->hasPerm('clients.edit_banking')
            || $user->hasRole('admin');
    }

    /** View sensitive financial data (card last4, brand, expiration) */
    public function viewSensitiveFinancial(User $user, Deal $deal): bool
    {
        return $user->hasPerm('clients.view_sensitive_financial');
    }

    /** Edit sensitive financial fields (only safe subset) */
    public function editSensitiveFinancial(User $user, Deal $deal): bool
    {
        return $user->hasPerm('clients.edit_sensitive_financial');
    }

    /** View payment profile (name on card, billing info) */
    public function viewPaymentProfile(User $user, Deal $deal): bool
    {
        return $user->hasPerm('clients.view_payment_profile')
            || $user->hasRole('admin');
    }

    /** Edit payment profile fields */
    public function editPaymentProfile(User $user, Deal $deal): bool
    {
        return $user->hasPerm('clients.edit_payment_profile')
            || $user->hasRole('admin');
    }

    /** View audit log history */
    public function viewAuditLogs(User $user, Deal $deal): bool
    {
        return $user->hasPerm('clients.view_audit_logs');
    }
}
