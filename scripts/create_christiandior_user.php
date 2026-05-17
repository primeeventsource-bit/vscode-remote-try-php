<?php
// One-shot: create the christiandior master_admin user expected by CrmNotePolicy.
// Run from repo root: php scripts/create_christiandior_user.php

$pdo = new PDO('sqlite:' . __DIR__ . '/../database/database.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$existing = $pdo->query("SELECT id FROM users WHERE LOWER(username) = 'christiandior'")->fetch();
if ($existing) {
    echo "User already exists (id={$existing['id']}). Aborting to avoid clobber.\n";
    exit(1);
}

$hash = password_hash('ChangeMe2026!', PASSWORD_BCRYPT);
$stmt = $pdo->prepare(
    'INSERT INTO users (username, name, email, role, password, status, created_at, updated_at)
     VALUES (:u, :n, :e, :r, :p, :s, datetime("now"), datetime("now"))'
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
echo "Inserted id={$id}\n";

foreach ($pdo->query("SELECT id, username, name, role, status FROM users WHERE id = {$id}") as $r) {
    echo "{$r['id']} | {$r['username']} | {$r['name']} | {$r['role']} | {$r['status']}\n";
}
