<?php
// Full read of crmprime.online prod DB: every table, row counts, sizes,
// and a sample from each populated table.

error_reporting(E_ERROR | E_PARSE);

$pdo = new PDO(
    'mysql:host=db-a16d4794-7407-4329-a986-e948ed7341c9.us-east-2.public.db.laravel.cloud;port=3306;dbname=main',
    'is8si14zpxokowdy',
    'l80NIUQiq24cbOcEcvuM',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$ver = $pdo->query('SELECT VERSION()')->fetchColumn();
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "=== DB SUMMARY ===\n";
echo "MySQL version: {$ver}\n";
echo "Database:      main\n";
echo "Table count:   " . count($tables) . "\n\n";

// information_schema column names alias to lowercase to dodge MySQL 8.4 quirks
$meta = [];
foreach ($pdo->query("
    SELECT TABLE_NAME AS t, TABLE_ROWS AS r, (DATA_LENGTH + INDEX_LENGTH) AS b
      FROM information_schema.tables
     WHERE TABLE_SCHEMA = 'main'
") as $row) {
    $meta[$row['t']] = ['bytes' => (int)$row['b']];
}

echo "=== ALL TABLES (exact row counts) ===\n";
printf("%-50s | %10s | %12s\n", 'table', 'rows', 'size');
echo str_repeat('-', 80) . "\n";

$populated = [];
$totalRows = 0;
foreach ($tables as $t) {
    $rows = (int) $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
    $bytes = $meta[$t]['bytes'] ?? 0;
    printf("%-50s | %10d | %12s\n", $t, $rows, fmtBytes($bytes));
    $totalRows += $rows;
    if ($rows > 0) $populated[$t] = $rows;
}
echo str_repeat('-', 80) . "\n";
printf("%-50s | %10d |\n", 'TOTAL', $totalRows);

echo "\n=== POPULATED TABLES (" . count($populated) . " of " . count($tables) . ") ===\n";
arsort($populated);
foreach ($populated as $t => $n) {
    printf("  %-50s %d rows\n", $t, $n);
}

echo "\n=== SAMPLE ROWS (up to 5 each) ===\n";
foreach (array_keys($populated) as $t) {
    echo "\n--- {$t} ---\n";
    $cols = [];
    foreach ($pdo->query("DESCRIBE `{$t}`") as $c) $cols[] = $c['Field'];
    $skip = ['password','remember_token','permissions','two_factor_secret','two_factor_recovery_codes','content','raw_summary_json','body'];
    $showCols = array_values(array_filter($cols, fn($c) => !in_array($c, $skip, true)));
    $select = '`' . implode('`,`', array_slice($showCols, 0, 7)) . '`';
    foreach ($pdo->query("SELECT {$select} FROM `{$t}` ORDER BY 1 DESC LIMIT 5") as $row) {
        $parts = [];
        foreach ($row as $k => $v) {
            if (is_int($k)) continue;
            $val = $v === null ? 'NULL' : (mb_strlen((string)$v) > 50 ? mb_substr((string)$v, 0, 50) . '…' : (string)$v);
            $parts[] = "{$k}={$val}";
        }
        echo "  " . implode(' | ', $parts) . "\n";
    }
}

function fmtBytes(int $b): string {
    if ($b < 1024) return "{$b} B";
    if ($b < 1024*1024) return number_format($b/1024, 1) . " KB";
    return number_format($b/1024/1024, 1) . " MB";
}
