<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time migration: refresh all existing user permissions to current role defaults.
 * Existing users may have stale permission sets from when they were created.
 * This ensures everyone has the correct permissions for their role.
 */
return new class extends Migration
{
    // Keep in sync with App\Console\Commands\RefreshUserPermissions::ROLE_DEFAULTS
    // and DatabaseSeeder. Already-migrated DBs are unaffected by edits here —
    // run `php artisan users:refresh-permissions` to apply changes to existing rows.
    private const CLIENT_PERMS_FULL = ['clients.view','clients.edit','clients.view_deal_sheet','clients.edit_deal_sheet','clients.view_banking','clients.edit_banking','clients.view_sensitive_financial','clients.edit_sensitive_financial','clients.view_payment_profile','clients.edit_payment_profile','clients.view_audit_logs'];
    private const CLIENT_PERMS_LIMITED = ['clients.view','clients.edit','clients.view_deal_sheet','clients.edit_deal_sheet','clients.view_banking','clients.edit_banking','clients.view_payment_profile','clients.edit_payment_profile'];

    private const ROLE_DEFAULTS = [
        'master_admin' => ['view_dashboard','view_stats','view_leads','view_all_leads','assign_leads','view_pipeline','view_deals','create_deals','view_verification','toggle_charged','toggle_chargeback','view_payroll','view_users','edit_users','delete_users','view_chat','view_documents','view_spreadsheets','master_override','import_csv','add_leads','disposition_leads','upload_files','view_login_info','create_chats', ...self::CLIENT_PERMS_FULL],
        'admin' => ['view_dashboard','view_stats','view_leads','view_all_leads','assign_leads','view_pipeline','view_deals','create_deals','view_verification','toggle_charged','toggle_chargeback','view_payroll','view_users','edit_users','delete_users','view_chat','view_documents','view_spreadsheets','import_csv','add_leads','disposition_leads','upload_files','view_login_info','create_chats', ...self::CLIENT_PERMS_FULL],
        'admin_limited' => ['view_dashboard','view_leads','view_pipeline','view_deals','view_verification','view_chat','view_documents','view_spreadsheets','view_all_leads','assign_leads','import_csv','add_leads','toggle_charged','toggle_chargeback','view_login_info','create_deals','create_chats','view_payroll', ...self::CLIENT_PERMS_LIMITED],
        'fronter' => ['view_leads','view_pipeline','view_chat','view_documents','view_spreadsheets','disposition_leads','create_chats','view_payroll','clients.view'],
        'fronter_panama' => ['view_leads','view_pipeline','view_chat','view_documents','view_spreadsheets','disposition_leads','create_chats','clients.view'],
        'closer' => ['view_dashboard','view_leads','view_pipeline','view_deals','view_verification','view_chat','view_documents','view_spreadsheets','disposition_leads','create_deals','create_chats','view_login_info','view_payroll','clients.view','clients.view_deal_sheet'],
        'closer_panama' => ['view_dashboard','view_leads','view_pipeline','view_deals','view_verification','view_chat','view_documents','view_spreadsheets','disposition_leads','create_deals','create_chats','view_login_info','clients.view','clients.view_deal_sheet'],
    ];

    public function up(): void
    {
        $users = DB::table('users')->get(['id', 'role', 'permissions']);

        foreach ($users as $user) {
            $role = $user->role ?? 'fronter';
            $correctPerms = self::ROLE_DEFAULTS[$role] ?? self::ROLE_DEFAULTS['fronter'];

            DB::table('users')->where('id', $user->id)->update([
                'permissions' => json_encode($correctPerms),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No rollback — permissions are always forward-only
    }
};
