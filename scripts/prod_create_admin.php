<?php
// Create the christiandior master_admin user on crmprime.online prod DB.
// Production env doesn't run seeders, so the users table is empty after migrate.
// Also satisfies the hardcoded gate in CrmNotePolicy.

$pdo = new PDO(
    'mysql:host=db-a16d4794-7407-4329-a986-e948ed7341c9.us-east-2.public.db.laravel.cloud;port=3306;dbname=main',
    'is8si14zpxokowdy',
    'l80NIUQiq24cbOcEcvuM',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$existing = $pdo->query("SELECT id FROM users WHERE LOWER(username) = 'christiandior'")->fetch();
if ($existing) {
    echo "Already exists: id={$existing['id']}\n";
    exit(0);
}

$hash = password_hash('ChangeMe2026!', PASSWORD_BCRYPT);
$stmt = $pdo->prepare(
    "INSERT INTO users (username, name, email, role, password, status, created_at, updated_at)
     VALUES (:u, :n, :e, :r, :p, :s, NOW(), NOW())"
);
$stmt->execute([
    ':u' => 'christiandior',
    ':n' => 'Christian Dior',
    ':e' => 'christiandior@primeeventsource.local',
    ':r' => 'master_admin',
    ':p' => $hash,
    ':s' => 'active',
]);

$id = $pdo->lastInsertId();
echo "Created user id={$id}\n";

$row = $pdo->query("SELECT id, username, name, role, status FROM users WHERE id={$id}")->fetch(PDO::FETCH_ASSOC);
echo "  {$row['id']} | {$row['username']} | {$row['name']} | {$row['role']} | {$row['status']}\n";

// Sanity: total user count
$count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
echo "Total users in DB: {$count}\n";
