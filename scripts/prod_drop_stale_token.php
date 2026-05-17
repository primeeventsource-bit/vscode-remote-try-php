<?php
// Drop the stale api_token row (expired 2026-05-10) from prod.
error_reporting(E_ERROR | E_PARSE);

$pdo = new PDO(
    'mysql:host=db-a16d4794-7407-4329-a986-e948ed7341c9.us-east-2.public.db.laravel.cloud;port=3306;dbname=main',
    'is8si14zpxokowdy',
    'l80NIUQiq24cbOcEcvuM',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "Before:\n";
foreach ($pdo->query("SELECT id, user_id, expires_at, created_at FROM api_tokens ORDER BY id") as $r) {
    echo "  id={$r['id']} user_id={$r['user_id']} created={$r['created_at']} expires={$r['expires_at']}\n";
}

// Only drop tokens that are already expired (safety: don't blow away a fresh session if user logged in)
$deleted = $pdo->exec("DELETE FROM api_tokens WHERE expires_at < NOW()");
echo "Deleted expired rows: $deleted\n";

echo "After:\n";
$remaining = $pdo->query("SELECT COUNT(*) FROM api_tokens")->fetchColumn();
echo "  api_tokens row count: $remaining\n";
