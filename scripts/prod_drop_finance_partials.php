<?php
// Drop the tables left behind by the partial run of
// 2026_04_08_000001_create_finance_command_center_tables migration so
// re-running migrate cleanly recreates them with the patched index name.

$pdo = new PDO(
    'mysql:host=db-a16d4794-7407-4329-a986-e948ed7341c9.us-east-2.public.db.laravel.cloud;port=3306;dbname=main',
    'is8si14zpxokowdy',
    'l80NIUQiq24cbOcEcvuM',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Tables the failing migration could have created (in dependency order, deepest-first for FK safety)
$candidates = [
    'finance_settings',
    'merchant_chargeback_events',
    'merchant_transaction_events',
    'merchant_financial_entries',
    'merchant_chargebacks',
    'merchant_transactions',
    'merchant_import_failures',
    'merchant_import_batches',
    'merchant_statement_line_items',
    'merchant_statement_summaries',
    'merchant_statement_uploads',
    // NOTE: NOT dropping `merchant_accounts` — earlier migration #19 owns it.
];

$existing = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Currently in DB: " . count($existing) . " tables.\n";
echo "Looking for partials from finance_command_center migration...\n";

$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
$dropped = 0;
foreach ($candidates as $t) {
    if (in_array($t, $existing, true)) {
        // Verify zero rows before dropping
        $n = (int) $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        if ($n > 0) {
            echo "  SKIP $t ($n rows — would be data loss)\n";
            continue;
        }
        $pdo->exec("DROP TABLE `{$t}`");
        echo "  dropped: $t\n";
        $dropped++;
    }
}
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");
echo "Total dropped: $dropped\n";
