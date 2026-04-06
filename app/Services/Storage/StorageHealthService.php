<?php

namespace App\Services\Storage;

use App\Models\StorageEvent;
use App\Models\StorageStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StorageHealthService
{
    /**
     * Run full health check on both disks. Returns array of results.
     * Updates DB status + triggers failover/failback if needed.
     */
    public static function runFullCheck(): array
    {
        $cfg = config('storage_resilience');
        if (! ($cfg['enabled'] ?? true)) {
            return ['skipped' => true, 'reason' => 'Storage resilience disabled'];
        }

        $primary  = $cfg['primary_disk'] ?? 'public';
        $fallback = $cfg['fallback_disk'] ?? 'local';

        $primaryResult  = self::checkDisk($primary);
        $fallbackResult = self::checkDisk($fallback);

        // Update status
        $status = StorageStatus::current();
        $oldState = $status->state;

        $status->primary_disk      = $primary;
        $status->fallback_disk     = $fallback;
        $status->primary_healthy   = $primaryResult['healthy'];
        $status->fallback_healthy  = $fallbackResult['healthy'];
        $status->primary_latency_ms  = $primaryResult['latency_ms'];
        $status->fallback_latency_ms = $fallbackResult['latency_ms'];
        $status->last_checked_at   = now();
        $status->details = [
            'primary'  => $primaryResult,
            'fallback' => $fallbackResult,
        ];

        // Failover logic
        if ($status->forced_disk) {
            // Admin override — respect it
            $status->active_disk = $status->forced_disk;
            $status->state = $primaryResult['healthy'] ? 'healthy' : 'failover_active';
        } elseif (! $primaryResult['healthy']) {
            $status->failure_count++;
            $status->recovery_count = 0;

            $threshold = $cfg['failure_threshold'] ?? 3;
            if ($status->failure_count >= $threshold && ($cfg['auto_failover_enabled'] ?? true)) {
                if ($fallbackResult['healthy'] && $status->active_disk !== $fallback) {
                    $status->active_disk = $fallback;
                    $status->state = 'failover_active';
                    $status->last_failover_at = now();
                    StorageEvent::log('failover_activated', "Switched to fallback disk: {$fallback}", 'critical', $primary, [
                        'failures' => $status->failure_count,
                        'primary_error' => $primaryResult['error'] ?? null,
                    ]);
                    Log::channel('stderr')->critical("Storage failover activated: {$primary} → {$fallback}");
                } elseif (! $fallbackResult['healthy']) {
                    $status->state = 'failed';
                    StorageEvent::log('both_disks_unhealthy', "Both {$primary} and {$fallback} are unhealthy", 'critical');
                }
            } else {
                $status->state = 'degraded';
            }
        } else {
            // Primary is healthy
            if ($status->active_disk === $fallback && $status->active_disk !== $primary && ! $status->forced_disk) {
                $status->recovery_count++;
                $recoveryThreshold = $cfg['recovery_threshold'] ?? 3;
                if ($status->recovery_count >= $recoveryThreshold && ($cfg['auto_failback_enabled'] ?? true)) {
                    $status->active_disk = $primary;
                    $status->state = 'healthy';
                    $status->failure_count = 0;
                    $status->recovery_count = 0;
                    $status->last_recovery_at = now();
                    StorageEvent::log('failback_activated', "Recovered to primary disk: {$primary}", 'info', $primary);
                    Log::channel('stderr')->info("Storage recovered: {$fallback} → {$primary}");
                }
            } else {
                $status->state = 'healthy';
                $status->failure_count = 0;
            }
        }

        try { $status->save(); } catch (\Throwable $e) {}

        StorageEvent::log('health_check', "{$primary}: " . ($primaryResult['healthy'] ? 'OK' : 'FAIL') . ", {$fallback}: " . ($fallbackResult['healthy'] ? 'OK' : 'FAIL'), $primaryResult['healthy'] ? 'info' : 'warning');

        return [
            'primary'  => $primaryResult,
            'fallback' => $fallbackResult,
            'active'   => $status->active_disk,
            'state'    => $status->state,
        ];
    }

    /**
     * Test a single disk: write → read → verify → delete.
     */
    public static function checkDisk(string $diskName): array
    {
        $testDir = config('storage_resilience.test_directory', '_storage_health_tests');
        $testFile = $testDir . '/health_' . time() . '_' . mt_rand(1000, 9999) . '.tmp';
        $testContent = 'health_check_' . now()->toIso8601String();
        $start = microtime(true);

        try {
            $disk = Storage::disk($diskName);

            // Write
            $written = $disk->put($testFile, $testContent);
            if (! $written) {
                return self::failResult($diskName, 'Write returned false', $start);
            }

            // Read back
            $readBack = $disk->get($testFile);
            if ($readBack !== $testContent) {
                $disk->delete($testFile);
                return self::failResult($diskName, 'Read-back mismatch', $start);
            }

            // Exists check
            if (! $disk->exists($testFile)) {
                return self::failResult($diskName, 'File not found after write', $start);
            }

            // Delete
            $disk->delete($testFile);

            $ms = (int) ((microtime(true) - $start) * 1000);

            return [
                'healthy'    => true,
                'disk'       => $diskName,
                'latency_ms' => $ms,
                'error'      => null,
            ];
        } catch (\Throwable $e) {
            // Cleanup attempt
            try { Storage::disk($diskName)->delete($testFile); } catch (\Throwable $e2) {}

            return self::failResult($diskName, $e->getMessage(), $start);
        }
    }

    private static function failResult(string $disk, string $error, float $start): array
    {
        return [
            'healthy'    => false,
            'disk'       => $disk,
            'latency_ms' => (int) ((microtime(true) - $start) * 1000),
            'error'      => $error,
        ];
    }

    /**
     * Quick check: is a disk healthy right now?
     */
    public static function isDiskHealthy(string $disk): bool
    {
        return self::checkDisk($disk)['healthy'];
    }
}
