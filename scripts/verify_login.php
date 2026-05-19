<?php
// Verify christiandior credentials via the same Auth::attempt path the login form uses.

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Credentials read from env to avoid committing a literal password.
// Set VERIFY_USERNAME and VERIFY_PASSWORD before running.
$username = getenv('VERIFY_USERNAME') ?: 'christiandior';
$password = getenv('VERIFY_PASSWORD');
if (!$password) {
    fwrite(STDERR, "Set VERIFY_PASSWORD env var before running this script.\n");
    exit(1);
}

$ok = \Illuminate\Support\Facades\Auth::attempt([
    'username' => $username,
    'password' => $password,
]);

echo "Auth::attempt = " . ($ok ? 'OK' : 'FAIL') . "\n";
if ($ok) {
    $u = \Illuminate\Support\Facades\Auth::user();
    echo "logged-in as: id={$u->id} username={$u->username} role={$u->role}\n";
}
