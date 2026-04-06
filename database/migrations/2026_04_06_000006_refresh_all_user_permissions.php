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
    private const ROLE_DEFAULTS = [
        'master_admin' => ['view_dashboard','view_stats','view_leads','view_all_leads','assign_leads','view_pipeline','view_deals','create_deals','view_verification','toggle_charged','toggle_chargeback','view_payroll','view_users','edit_users','delete_users','view_chat','view_documents','view_spreadsheets','master_override','import_csv','add_leads','disposition_leads','upload_files','view_login_info','create_chats'],
        'admin' => ['view_dashboard','view_stats','view_leads','view_all_leads','assign_leads','view_pipeline','view_deals','create_deals','view_verification','toggle_charged','toggle_chargeback','view_payroll','view_users','edit_users','delete_users','view_chat','view_documents','view_spreadsheets','import_csv','add_leads','disposition_leads','upload_files','view_login_info','create_chats'],
        'fronter' => ['view_leads','view_pipeline','view_chat','view_documents','view_spreadsheets','disposition_leads','create_chats','view_payroll'],
        'fronter_panama' => ['view_leads','view_pipeline','view_chat','view_documents','view_spreadsheets','disposition_leads','create_chats'],
        'closer' => ['view_dashboard','view_leads','view_pipeline','view_deals','view_verification','view_chat','view_documents','view_spreadsheets','disposition_leads','create_deals','create_chats','view_login_info','view_payroll'],
        'closer_panama' => ['view_dashboard','view_leads','view_pipeline','view_deals','view_verification','view_chat','view_documents','view_spreadsheets','disposition_leads','create_deals','create_chats','view_login_info'],
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
