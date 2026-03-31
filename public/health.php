<?php
// Raw health check - bypasses Laravel entirely
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'php' => PHP_VERSION,
    'env_exists' => file_exists(__DIR__ . '/../.env'),
    'app_key_set' => !empty(trim(explode('APP_KEY=', file_get_contents(__DIR__ . '/../.env') ?: '')[1] ?? '')),
    'vendor_exists' => is_dir(__DIR__ . '/../vendor'),
    'bootstrap_cache' => is_dir(__DIR__ . '/../bootstrap/cache') && is_writable(__DIR__ . '/../bootstrap/cache'),
    'storage_views' => is_dir(__DIR__ . '/../storage/framework/views') && is_writable(__DIR__ . '/../storage/framework/views'),
    'storage_sessions' => is_dir(__DIR__ . '/../storage/framework/sessions') && is_writable(__DIR__ . '/../storage/framework/sessions'),
    'storage_cache' => is_dir(__DIR__ . '/../storage/framework/cache') && is_writable(__DIR__ . '/../storage/framework/cache'),
    'storage_logs' => is_dir(__DIR__ . '/../storage/logs') && is_writable(__DIR__ . '/../storage/logs'),
]);
