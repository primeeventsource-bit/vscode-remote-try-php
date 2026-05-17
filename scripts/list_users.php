<?php
// List users in both local sqlite + prod MySQL.

function dump(PDO $pdo, string $label): void {
    echo "=== {$label} ===\n";
    try {
        $rows = $pdo->query(
            "SELECT id, username, name, email, role, status, created_at
             FROM users ORDER BY id"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        echo "ERROR: {$e->getMessage()}\n\n";
        return;
    }
    if (!$rows) { echo "(no users)\n\n"; return; }

    printf("%-3s | %-15s | %-22s | %-38s | %-15s | %-8s | %s\n",
        'id', 'username', 'name', 'email', 'role', 'status', 'created_at');
    echo str_repeat('-', 130) . "\n";
    foreach ($rows as $r) {
        printf("%-3s | %-15s | %-22s | %-38s | %-15s | %-8s | %s\n",
            $r['id'], $r['username'] ?? '', $r['name'] ?? '',
            $r['email'] ?? '', $r['role'] ?? '', $r['status'] ?? '',
            $r['created_at'] ?? '');
    }
    echo "\nTotal: " . count($rows) . "\n\n";
}

// Local sqlite
$local = new PDO('sqlite:' . __DIR__ . '/../database/database.sqlite');
$local->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
dump($local, 'LOCAL  (database/database.sqlite)');

// Prod MySQL
$prod = new PDO(
    'mysql:host=db-a16d4794-7407-4329-a986-e948ed7341c9.us-east-2.public.db.laravel.cloud;port=3306;dbname=main',
    'is8si14zpxokowdy',
    'l80NIUQiq24cbOcEcvuM',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
dump($prod, 'PROD   (crmprime.online MySQL)');
