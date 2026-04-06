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

    private const ROLE_DEFAULTS = [
        'master_admin' => [
            'view_dashboard', 'view_stats', 'view_leads', 'view_all_leads', 'assign_leads',
            'view_pipeline', 'view_deals', 'create_deals', 'view_verification',
            'toggle_charged', 'toggle_chargeback', 'view_payroll',
            'view_users', 'edit_users', 'delete_users',
            'view_chat', 'view_documents', 'view_spreadsheets',
            'master_override', 'import_csv', 'add_leads', 'disposition_leads',
            'upload_files', 'view_login_info', 'create_chats',
        ],
        'admin' => [
            'view_dashboard', 'view_stats', 'view_leads', 'view_all_leads', 'assign_leads',
            'view_pipeline', 'view_deals', 'create_deals', 'view_verification',
            'toggle_charged', 'toggle_chargeback', 'view_payroll',
            'view_users', 'edit_users', 'delete_users',
            'view_chat', 'view_documents', 'view_spreadsheets',
            'import_csv', 'add_leads', 'disposition_leads',
            'upload_files', 'view_login_info', 'create_chats',
        ],
        'fronter' => [
            'view_leads', 'view_pipeline', 'view_chat',
            'view_documents', 'view_spreadsheets',
            'disposition_leads', 'create_chats', 'view_payroll',
        ],
        'fronter_panama' => [
            'view_leads', 'view_pipeline', 'view_chat',
            'view_documents', 'view_spreadsheets',
            'disposition_leads', 'create_chats',
        ],
        'closer' => [
            'view_dashboard', 'view_leads', 'view_pipeline',
            'view_deals', 'view_verification', 'view_chat',
            'view_documents', 'view_spreadsheets',
            'disposition_leads', 'create_deals', 'create_chats',
            'view_login_info', 'view_payroll',
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
