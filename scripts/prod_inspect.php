<?php
// Quick read-only inspection of crmprime.online's production MySQL.
// Uses the public endpoint + credentials from cloud db-cluster:list.

$pdo = new PDO(
    'mysql:host=db-a16d4794-7407-4329-a986-e948ed7341c9.us-east-2.public.db.laravel.cloud;port=3306;dbname=main',
    'is8si14zpxokowdy',
    'l80NIUQiq24cbOcEcvuM',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== migrations table ===\n";
$rows = $pdo->query('SELECT COUNT(*) AS n FROM migrations')->fetch();
echo "rows: {$rows['n']}\n";
foreach ($pdo->query('SELECT * FROM migrations ORDER BY batch, id LIMIT 10') as $r) {
    echo "  batch={$r['batch']} {$r['migration']}\n";
}

echo "\n=== row counts ===\n";
$tables = ['users','leads','deals','calls','campaigns','contracts','payments','bookings','tenants','sessions'];
foreach ($tables as $t) {
    try {
        $c = $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        echo str_pad($t, 28) . ": {$c}\n";
    } catch (Throwable $e) {
        echo str_pad($t, 28) . ": MISSING\n";
    }
}

echo "\n=== users sample ===\n";
$stmt = $pdo->query('SELECT id, username, name, role, status FROM users ORDER BY id LIMIT 20');
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  id={$r['id']} u={$r['username']} role={$r['role']} status={$r['status']} name={$r['name']}\n";
}
