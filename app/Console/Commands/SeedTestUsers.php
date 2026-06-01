<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedTestUsers extends Command
{
    protected $signature = 'user:seed-test-users
        {--password=Test2026! : Shared password for every test user}
        {--prefix=test_ : Username prefix}';

    protected $description = 'Create or reset one test user per role (idempotent). All share the same password.';

    public function handle(): int
    {
        $password = $this->option('password');
        $prefix = $this->option('prefix');

        $clientPerms = [
            'clients.view','clients.edit',
            'clients.view_deal_sheet','clients.edit_deal_sheet',
            'clients.view_banking','clients.edit_banking',
            'clients.view_sensitive_financial','clients.edit_sensitive_financial',
            'clients.view_payment_profile','clients.edit_payment_profile',
            'clients.view_audit_logs',
        ];

        $allPerms = [
            'view_dashboard','view_stats','view_leads','view_pipeline','view_deals',
            'view_verification','view_chat','view_users','import_csv','add_leads',
            'assign_leads','view_all_leads','disposition_leads','create_deals',
            'toggle_charged','toggle_chargeback','upload_files','view_login_info',
            'create_chats','view_payroll','edit_payroll','manage_payroll',
            'edit_users','delete_users','master_override',
            ...$clientPerms,
        ];
        $adminPerms = array_values(array_filter($allPerms, fn ($k) => $k !== 'master_override'));
        $adminLimitedPerms = [
            'view_dashboard','view_leads','view_pipeline','view_deals','view_verification',
            'view_chat','view_all_leads','assign_leads','import_csv','add_leads',
            'toggle_charged','toggle_chargeback','view_login_info','create_deals',
            'create_chats','view_payroll',
            'clients.view','clients.edit',
            'clients.view_deal_sheet','clients.edit_deal_sheet',
            'clients.view_banking','clients.edit_banking',
            'clients.view_payment_profile','clients.edit_payment_profile',
        ];
        $fronterPerms = [
            'view_leads','view_pipeline','view_chat','disposition_leads','create_chats','view_payroll',
            'clients.view',
        ];
        $closerPerms = [
            'view_dashboard','view_leads','view_pipeline','view_deals','view_verification',
            'view_chat','disposition_leads','create_deals','create_chats','view_login_info','view_payroll',
            'clients.view','clients.view_deal_sheet',
        ];

        $roles = [
            ['role' => 'master_admin',   'perms' => $allPerms,          'color' => '#7c3aed'],
            ['role' => 'admin',          'perms' => $adminPerms,        'color' => '#3b82f6'],
            ['role' => 'admin_limited',  'perms' => $adminLimitedPerms, 'color' => '#10b981'],
            ['role' => 'fronter',        'perms' => $fronterPerms,      'color' => '#ec4899'],
            ['role' => 'fronter_panama', 'perms' => $fronterPerms,      'color' => '#f59e0b'],
            ['role' => 'closer',         'perms' => $closerPerms,       'color' => '#8b5cf6'],
            ['role' => 'closer_panama',  'perms' => $closerPerms,       'color' => '#14b8a6'],
        ];

        $rows = [];
        foreach ($roles as $r) {
            $username = $prefix . $r['role'];
            $user = User::updateOrCreate(
                ['username' => $username],
                [
                    'name'     => 'Test ' . ucwords(str_replace('_', ' ', $r['role'])),
                    'email'    => "{$username}@primeeventsource.local",
                    'status'   => 'active',
                    'avatar'   => strtoupper(substr($r['role'], 0, 2)),
                    'color'    => $r['color'],
                    'password' => Hash::make($password),
                ],
            );
            $user->role = $r['role'];
            $user->permissions = $r['perms'];
            $user->save();

            $rows[] = [
                $user->wasRecentlyCreated ? 'new' : 'updated',
                $user->id,
                $username,
                $r['role'],
                $password,
            ];
        }

        $this->table(['action','id','username','role','password'], $rows);
        return self::SUCCESS;
    }
}
