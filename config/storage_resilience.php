<?php

return [

    'enabled' => env('STORAGE_RESILIENCE_ENABLED', true),

    // Primary and fallback disks (must match config/filesystems.php disk names)
    'primary_disk'  => env('STORAGE_PRIMARY_DISK', 'public'),
    'fallback_disk' => env('STORAGE_FALLBACK_DISK', 'local'),

    // Health check settings
    'health_check_interval_seconds' => 300,  // 5 minutes
    'failure_threshold'             => 3,    // consecutive failures before failover
    'recovery_threshold'            => 3,    // consecutive successes before failback

    // Failover behavior
    'auto_failover_enabled'  => true,
    'auto_failback_enabled'  => true,
    'verify_after_write'     => true,
    'verify_public_urls'     => true,

    // Alerting
    'alert_on_failover'  => true,
    'alert_on_recovery'  => true,
    'alert_throttle_min' => 30,  // minutes between repeated alerts

    // Housekeeping
    'retention_days_for_logs' => 30,
    'test_directory'          => '_storage_health_tests',

];
