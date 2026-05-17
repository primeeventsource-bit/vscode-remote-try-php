<?php
// Surgical reset of crmprime.online's MySQL `main` schema.
// All 28 tables have 0 rows and incompatible schema vs current codebase.
// Drops every table in `main` so `php artisan migrate --force` can rebuild clean.

$pdo = new PDO(
    'mysql:host=db-a16d4794-7407-4329-a986-e948ed7341c9.us-east-2.public.db.laravel.cloud;port=3306;dbname=main',
    'is8si14zpxokowdy',
    'l80NIUQiq24cbOcEcvuM',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Final pre-flight: verify zero rows everywhere. If anything has data, abort.
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables before drop: " . count($tables) . "\n";
// `migrations` is Laravel-managed metadata (not user data) and is expected
// to hold the 9 orphan rows we're explicitly clearing. Ignore for safety check.
$dataTables = array_diff($tables, ['migrations']);
$totalRows = 0;
foreach ($dataTables as $t) {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
    if ($n > 0) {
        echo "  $t: $n rows\n";
        $totalRows += $n;
    }
}
if ($totalRows > 0) {
    echo "ABORT: found {$totalRows} rows in user-data tables. Refusing to drop.\n";
    exit(1);
}
echo "Confirmed: all data tables empty (migrations metadata: " .
     $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn() . " rows, will be cleared).\n\n";

echo "Dropping tables (FK checks off)...\n";
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
foreach ($tables as $t) {
    $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
    echo "  dropped: $t\n";
}
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");

$after = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "\nTables after drop: " . count($after) . "\n";
