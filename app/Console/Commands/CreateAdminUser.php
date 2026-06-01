<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'user:create-admin
        {username=christiandior : Username for the admin}
        {--name= : Display name (defaults to ucfirst(username))}
        {--email= : Email (defaults to <username>@primeeventsource.local)}
        {--password=ChangeMe2026! : Initial password (use to reset on re-run)}
        {--role=master_admin : Role to assign}';

    protected $description = 'Create or reset an admin user (idempotent — re-running resets password/role/permissions).';

    public function handle(): int
    {
        $username = $this->argument('username');
        $name = $this->option('name') ?: ucfirst($username);
        $email = $this->option('email') ?: "{$username}@primeeventsource.local";
        $password = $this->option('password');
        $role = $this->option('role');

        $allPerms = [
            'view_dashboard','view_stats','view_leads','view_pipeline','view_deals',
            'view_verification','view_chat','view_users','import_csv','add_leads',
            'assign_leads','view_all_leads','disposition_leads','create_deals',
            'toggle_charged','toggle_chargeback','upload_files','view_login_info',
            'create_chats','view_payroll','edit_payroll','manage_payroll',
            'edit_users','delete_users','master_override',
            'clients.view','clients.edit',
            'clients.view_deal_sheet','clients.edit_deal_sheet',
            'clients.view_banking','clients.edit_banking',
            'clients.view_sensitive_financial','clients.edit_sensitive_financial',
            'clients.view_payment_profile','clients.edit_payment_profile',
            'clients.view_audit_logs',
        ];
        $perms = $role === 'master_admin'
            ? $allPerms
            : array_values(array_filter($allPerms, fn ($k) => $k !== 'master_override'));

        $user = User::updateOrCreate(
            ['username' => $username],
            [
                'name'     => $name,
                'email'    => $email,
                'status'   => 'active',
                'avatar'   => strtoupper(substr($username, 0, 2)),
                'color'    => '#7c3aed',
                'password' => Hash::make($password),
            ],
        );

        $user->role = $role;
        $user->permissions = $perms;
        $user->save();

        $action = $user->wasRecentlyCreated ? 'Created' : 'Updated';
        $this->info("{$action} {$role}: id={$user->id} username={$username} password={$password}");

        return self::SUCCESS;
    }
}
