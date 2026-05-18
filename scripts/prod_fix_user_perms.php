<?php
/**
 * Prod-safe user permission refresh.
 *
 * Two operations:
 *   1) Hard-guarantee christiandior can use the app: grant master_override.
 *      (christiandior was created with permissions = [] which hid every nav item.)
 *   2) Optionally sync all users to current role defaults (matches the updated
 *      app/Console/Commands/RefreshUserPermissions::ROLE_DEFAULTS — admin_limited
 *      and clients.* perms included so admins keep PAN view + audit logs and
 *      fronters keep Clients page access).
 *
 * Usage:
 *   php scripts/prod_fix_user_perms.php --dry-run            # preview only
 *   php scripts/prod_fix_user_perms.php --christiandior-only # patch one account
 *   php scripts/prod_fix_user_perms.php                      # full refresh
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dryRun           = in_array('--dry-run', $argv ?? [], true);
$christianOnly    = in_array('--christiandior-only', $argv ?? [], true);

$CLIENT_PERMS_FULL = ['clients.view','clients.edit','clients.view_deal_sheet','clients.edit_deal_sheet','clients.view_banking','clients.edit_banking','clients.view_sensitive_financial','clients.edit_sensitive_financial','clients.view_payment_profile','clients.edit_payment_profile','clients.view_audit_logs'];
$CLIENT_PERMS_LIMITED = ['clients.view','clients.edit','clients.view_deal_sheet','clients.edit_deal_sheet','clients.view_banking','clients.edit_banking','clients.view_payment_profile','clients.edit_payment_profile'];

$ROLE_DEFAULTS = [
    'master_admin' => array_merge(['view_dashboard','view_stats','view_leads','view_all_leads','assign_leads','view_pipeline','view_deals','create_deals','view_verification','toggle_charged','toggle_chargeback','view_payroll','view_users','edit_users','delete_users','view_chat','view_documents','view_spreadsheets','master_override','import_csv','add_leads','disposition_leads','upload_files','view_login_info','create_chats'], $CLIENT_PERMS_FULL),
    'admin' => array_merge(['view_dashboard','view_stats','view_leads','view_all_leads','assign_leads','view_pipeline','view_deals','create_deals','view_verification','toggle_charged','toggle_chargeback','view_payroll','view_users','edit_users','delete_users','view_chat','view_documents','view_spreadsheets','import_csv','add_leads','disposition_leads','upload_files','view_login_info','create_chats'], $CLIENT_PERMS_FULL),
    'admin_limited' => array_merge(['view_dashboard','view_leads','view_pipeline','view_deals','view_verification','view_chat','view_documents','view_spreadsheets','view_all_leads','assign_leads','import_csv','add_leads','toggle_charged','toggle_chargeback','view_login_info','create_deals','create_chats','view_payroll'], $CLIENT_PERMS_LIMITED),
    'fronter' => ['view_leads','view_pipeline','view_chat','view_documents','view_spreadsheets','disposition_leads','create_chats','view_payroll','clients.view'],
    'fronter_panama' => ['view_leads','view_pipeline','view_chat','view_documents','view_spreadsheets','disposition_leads','create_chats','clients.view'],
    'closer' => ['view_dashboard','view_leads','view_pipeline','view_deals','view_verification','view_chat','view_documents','view_spreadsheets','disposition_leads','create_deals','create_chats','view_login_info','view_payroll','clients.view','clients.view_deal_sheet'],
    'closer_panama' => ['view_dashboard','view_leads','view_pipeline','view_deals','view_verification','view_chat','view_documents','view_spreadsheets','disposition_leads','create_deals','create_chats','view_login_info','clients.view','clients.view_deal_sheet'],
];

$mode = $dryRun ? '[DRY RUN] ' : '';
echo $mode . "Connection: " . config('database.default') . "\n";
echo $mode . "Mode: " . ($christianOnly ? 'christiandior only' : 'all users') . "\n\n";

// (1) Christiandior unconditional fix
$cd = \App\Models\User::where('username', 'christiandior')->first();
if ($cd) {
    $cdPerms = is_array($cd->permissions) ? $cd->permissions : json_decode($cd->permissions ?? '[]', true);
    if (!in_array('master_override', $cdPerms ?? [])) {
        $newPerms = array_values(array_unique(array_merge($cdPerms ?? [], ['master_override'])));
        echo $mode . "christiandior (#{$cd->id}): grant master_override (perms " . count($cdPerms ?? []) . " -> " . count($newPerms) . ")\n";
        if (!$dryRun) {
            $cd->permissions = $newPerms;
            $cd->save();
        }
    } else {
        echo "christiandior (#{$cd->id}): already has master_override, no change\n";
    }
} else {
    echo "christiandior: user not found, skipping\n";
}

if ($christianOnly) {
    echo "\nDone.\n";
    exit(0);
}

// (2) Full role-defaults refresh
echo "\n" . $mode . "Refreshing role defaults for all users...\n";
$updated = 0;
foreach (\App\Models\User::all() as $user) {
    $role = $user->role ?? 'fronter';
    $correct = $ROLE_DEFAULTS[$role] ?? null;
    if ($correct === null) {
        echo "  [{$role}] {$user->username} (#{$user->id}): UNKNOWN ROLE, skipping\n";
        continue;
    }
    $current = is_array($user->permissions) ? $user->permissions : json_decode($user->permissions ?? '[]', true);
    $current = $current ?? [];

    $sortedCorrect = $correct; sort($sortedCorrect);
    $sortedCurrent = $current; sort($sortedCurrent);
    if ($sortedCorrect === $sortedCurrent) continue;

    $added   = array_diff($correct, $current);
    $removed = array_diff($current, $correct);
    echo "  [{$role}] {$user->username} (#{$user->id})\n";
    if ($added)   echo "    + " . implode(', ', $added) . "\n";
    if ($removed) echo "    - " . implode(', ', $removed) . "\n";

    if (!$dryRun) {
        $user->permissions = $correct;
        $user->save();
    }
    $updated++;
}
echo "\n" . $mode . "{$updated} user(s) " . ($dryRun ? "would be" : "were") . " updated.\n";
