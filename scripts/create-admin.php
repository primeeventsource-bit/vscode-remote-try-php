<?php
/**
 * One-off script to create or update a master_admin user.
 * Usage: php scripts/create-admin.php <username> <password> [name] [email]
 * Safe to delete after running.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? null;
$name     = $argv[3] ?? 'Local Admin';
$email    = $argv[4] ?? "{$username}@local.test";

if (! $password) {
    fwrite(STDERR, "Password is required as second arg.\n");
    exit(1);
}

// Mirror the permission set used in DatabaseSeeder for master_admin.
$clientPerms = [
    'clients.view', 'clients.edit',
    'clients.view_deal_sheet', 'clients.edit_deal_sheet',
    'clients.view_banking', 'clients.edit_banking',
    'clients.view_sensitive_financial', 'clients.edit_sensitive_financial',
    'clients.view_payment_profile', 'clients.edit_payment_profile',
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

DB::table('users')->updateOrInsert(
    ['username' => $username],
    [
        'name'        => $name,
        'email'       => $email,
        'role'        => 'master_admin',
        'avatar'      => strtoupper(substr($name, 0, 2)),
        'color'       => '#0ea5e9',
        'status'      => 'online',
        'username'    => $username,
        'password'    => Hash::make($password),
        'permissions' => json_encode($allPerms),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]
);

echo "✓ master_admin user '{$username}' created/updated.\n";
echo "  Login: {$username} / {$password}\n";
