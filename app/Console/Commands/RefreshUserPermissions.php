<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Refreshes all user permissions to match current role defaults.
 * Run after deploying permission changes to ensure existing users
 * get the updated permission sets for their role.
 */
class RefreshUserPermissions extends Command
{
    protected $signature = 'users:refresh-permissions {--dry-run : Show what would change without saving}';
    protected $description = 'Sync all user permissions to current role defaults';

    // Granular clients.* perms gating ClientPolicy methods. Three of these
    // (view_sensitive_financial, edit_sensitive_financial, view_audit_logs)
    // have NO role-based fallback in ClientPolicy — only master_override
    // bypasses them. Strip them from a non-master_admin admin and PAN view
    // + audit log disappear. Keep this list in sync with DatabaseSeeder.
    private const CLIENT_PERMS_FULL = [
        'clients.view', 'clients.edit',
        'clients.view_deal_sheet', 'clients.edit_deal_sheet',
        'clients.view_banking', 'clients.edit_banking',
        'clients.view_sensitive_financial', 'clients.edit_sensitive_financial',
        'clients.view_payment_profile', 'clients.edit_payment_profile',
        'clients.view_audit_logs',
    ];

    // admin_limited == admin minus sensitive_financial + audit_logs.
    private const CLIENT_PERMS_LIMITED = [
        'clients.view', 'clients.edit',
        'clients.view_deal_sheet', 'clients.edit_deal_sheet',
        'clients.view_banking', 'clients.edit_banking',
        'clients.view_payment_profile', 'clients.edit_payment_profile',
    ];

    private const ROLE_DEFAULTS = [
        'master_admin' => [
            'view_dashboard', 'view_stats', 'view_leads', 'view_all_leads', 'assign_leads',
            'view_pipeline', 'view_deals', 'create_deals', 'view_verification',
            'toggle_charged', 'toggle_chargeback', 'view_payroll',
            'view_users', 'edit_users', 'delete_users',
            'view_chat', 'view_documents', 'view_spreadsheets',
            'master_override', 'import_csv', 'add_leads', 'disposition_leads',
            'upload_files', 'view_login_info', 'create_chats',
            ...self::CLIENT_PERMS_FULL,
        ],
        'admin' => [
            'view_dashboard', 'view_stats', 'view_leads', 'view_all_leads', 'assign_leads',
            'view_pipeline', 'view_deals', 'create_deals', 'view_verification',
            'toggle_charged', 'toggle_chargeback', 'view_payroll',
            'view_users', 'edit_users', 'delete_users',
            'view_chat', 'view_documents', 'view_spreadsheets',
            'import_csv', 'add_leads', 'disposition_leads',
            'upload_files', 'view_login_info', 'create_chats',
            ...self::CLIENT_PERMS_FULL,
        ],
        'admin_limited' => [
            'view_dashboard', 'view_leads', 'view_pipeline', 'view_deals', 'view_verification',
            'view_chat', 'view_documents', 'view_spreadsheets',
            'view_all_leads', 'assign_leads', 'import_csv', 'add_leads',
            'toggle_charged', 'toggle_chargeback', 'view_login_info', 'create_deals',
            'create_chats', 'view_payroll',
            ...self::CLIENT_PERMS_LIMITED,
        ],
        'fronter' => [
            'view_leads', 'view_pipeline', 'view_chat',
            'view_documents', 'view_spreadsheets',
            'disposition_leads', 'create_chats', 'view_payroll',
            'clients.view',
        ],
        'fronter_panama' => [
            'view_leads', 'view_pipeline', 'view_chat',
            'view_documents', 'view_spreadsheets',
            'disposition_leads', 'create_chats',
            'clients.view',
        ],
        'closer' => [
            'view_dashboard', 'view_leads', 'view_pipeline',
            'view_deals', 'view_verification', 'view_chat',
            'view_documents', 'view_spreadsheets',
            'disposition_leads', 'create_deals', 'create_chats',
            'view_login_info', 'view_payroll',
            'clients.view', 'clients.view_deal_sheet',
        ],
        'closer_panama' => [
            'view_dashboard', 'view_leads', 'view_pipeline',
            'view_deals', 'view_verification', 'view_chat',
            'view_documents', 'view_spreadsheets',
            'disposition_leads', 'create_deals', 'create_chats',
            'view_login_info',
            'clients.view', 'clients.view_deal_sheet',
        ],
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $users = User::all();
        $updated = 0;

        foreach ($users as $user) {
            $role = $user->role ?? 'fronter';
            $correctPerms = self::ROLE_DEFAULTS[$role] ?? self::ROLE_DEFAULTS['fronter'];
            $currentPerms = is_array($user->permissions) ? $user->permissions : json_decode($user->permissions ?? '[]', true);

            sort($correctPerms);
            $sortedCurrent = $currentPerms;
            sort($sortedCurrent);

            if ($sortedCurrent !== $correctPerms) {
                $added = array_diff($correctPerms, $currentPerms);
                $removed = array_diff($currentPerms, $correctPerms);

                $this->line("  [{$user->role}] {$user->name} (#{$user->id})");
                if (!empty($added)) $this->line("    <fg=green>+ " . implode(', ', $added) . "</>");
                if (!empty($removed)) $this->line("    <fg=red>- " . implode(', ', $removed) . "</>");

                if (!$dryRun) {
                    $user->permissions = $correctPerms;
                    $user->save();
                }
                $updated++;
            }
        }

        if ($dryRun) {
            $this->info("{$updated} user(s) would be updated. Run without --dry-run to apply.");
        } else {
            $this->info("{$updated} user(s) updated with current role permissions.");
        }

        return 0;
    }
}
