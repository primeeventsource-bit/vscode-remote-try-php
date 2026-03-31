<?php
header('Content-Type: application/json');

$envFile = __DIR__ . '/../.env';
$envContent = file_exists($envFile) ? file_get_contents($envFile) : '';
$envLines = [];
foreach (explode("\n", $envContent) as $line) {
    $line = trim($line);
    if ($line && $line[0] !== '#' && strpos($line, '=') !== false) {
        [$k] = explode('=', $line, 2);
        $envLines[] = trim($k);
    }
}

echo json_encode([
    'status' => 'ok',
    'php' => PHP_VERSION,
    'env_file_exists' => file_exists($envFile),
    'env_file_size' => file_exists($envFile) ? filesize($envFile) : 0,
    'env_keys_found' => $envLines,
    'env_APP_KEY' => getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? ($_SERVER['APP_KEY'] ?? 'NOT SET')),
    'vendor_exists' => is_dir(__DIR__ . '/../vendor'),
    'bootstrap_cache_writable' => is_dir(__DIR__ . '/../bootstrap/cache') && is_writable(__DIR__ . '/../bootstrap/cache'),
    'storage_views_writable' => is_dir(__DIR__ . '/../storage/framework/views') && is_writable(__DIR__ . '/../storage/framework/views'),
    'storage_logs_exists' => is_dir(__DIR__ . '/../storage/logs'),
    'storage_logs_writable' => is_dir(__DIR__ . '/../storage/logs') && is_writable(__DIR__ . '/../storage/logs'),
    'cwd' => getcwd(),
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
], JSON_PRETTY_PRINT);
