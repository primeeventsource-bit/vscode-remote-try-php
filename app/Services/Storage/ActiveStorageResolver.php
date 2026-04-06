<?php

namespace App\Services\Storage;

use App\Models\StorageEvent;
use App\Models\StorageStatus;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Central storage resolver. ALL file operations should go through this
 * instead of calling Storage::disk('public') directly.
 *
 * Usage:
 *   $resolver = app(ActiveStorageResolver::class);
 *   $path = $resolver->put('avatars/photo.jpg', $contents);
 *   $url  = $resolver->url($path);
 */
class ActiveStorageResolver
{
    private ?string $activeDisk = null;

    /**
     * Get the currently active disk name.
     */
    public function currentDisk(): string
    {
        if ($this->activeDisk) return $this->activeDisk;

        try {
            $status = StorageStatus::current();
            $this->activeDisk = $status->forced_disk ?? $status->active_disk ?? config('storage_resilience.primary_disk', 'public');
        } catch (\Throwable $e) {
            $this->activeDisk = config('storage_resilience.primary_disk', 'public');
        }

        return $this->activeDisk;
    }

    /**
     * Get the Storage disk instance.
     */
    public function disk(): Filesystem
    {
        return Storage::disk($this->currentDisk());
    }

    /**
     * Store a file with optional post-write verification.
     */
    public function put(string $path, $contents, array $options = []): bool
    {
        $disk = $this->currentDisk();
        try {
            $result = Storage::disk($disk)->put($path, $contents, $options);

            if ($result && config('storage_resilience.verify_after_write', true)) {
                if (! Storage::disk($disk)->exists($path)) {
                    StorageEvent::log('write_verify_failed', "File not found after write: {$path}", 'warning', $disk);
                    return $this->retryOnFallback($path, $contents, $options);
                }
            }

            return $result;
        } catch (\Throwable $e) {
            StorageEvent::log('write_failure', "Write failed on {$disk}: {$e->getMessage()}", 'critical', $disk, ['path' => $path]);
            return $this->retryOnFallback($path, $contents, $options);
        }
    }

    /**
     * Store an uploaded file (UploadedFile->store()).
     */
    public function storeUpload($file, string $directory): ?string
    {
        $disk = $this->currentDisk();
        try {
            $path = $file->store($directory, $disk);

            if ($path && config('storage_resilience.verify_after_write', true)) {
                if (! Storage::disk($disk)->exists($path)) {
                    StorageEvent::log('upload_verify_failed', "Upload not found after store: {$path}", 'warning', $disk);
                    // Retry on fallback
                    $fallback = $this->fallbackDisk();
                    if ($fallback !== $disk) {
                        $path = $file->store($directory, $fallback);
                        StorageEvent::log('upload_fallback_used', "Upload stored on fallback: {$path}", 'warning', $fallback);
                    }
                }
            }

            return $path;
        } catch (\Throwable $e) {
            StorageEvent::log('upload_failure', "Upload failed on {$disk}: {$e->getMessage()}", 'critical', $disk);
            $fallback = $this->fallbackDisk();
            if ($fallback !== $disk) {
                try {
                    return $file->store($directory, $fallback);
                } catch (\Throwable $e2) {
                    StorageEvent::log('upload_failure', "Upload also failed on fallback {$fallback}", 'critical', $fallback);
                }
            }
            return null;
        }
    }

    /**
     * Get file contents — tries active disk, then other disk.
     */
    public function get(string $path): ?string
    {
        $disk = $this->currentDisk();
        try {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->get($path);
            }
        } catch (\Throwable $e) {}

        // Try other disk
        $other = $this->otherDisk($disk);
        try {
            if (Storage::disk($other)->exists($path)) {
                return Storage::disk($other)->get($path);
            }
        } catch (\Throwable $e) {}

        return null;
    }

    /**
     * Check if file exists — checks both disks.
     */
    public function exists(string $path): bool
    {
        $disk = $this->currentDisk();
        try { if (Storage::disk($disk)->exists($path)) return true; } catch (\Throwable $e) {}

        $other = $this->otherDisk($disk);
        try { if (Storage::disk($other)->exists($path)) return true; } catch (\Throwable $e) {}

        return false;
    }

    /**
     * Delete a file from whichever disk it's on.
     */
    public function delete(string $path): bool
    {
        $deleted = false;
        foreach ([$this->currentDisk(), $this->otherDisk($this->currentDisk())] as $d) {
            try {
                if (Storage::disk($d)->exists($path)) {
                    Storage::disk($d)->delete($path);
                    $deleted = true;
                }
            } catch (\Throwable $e) {}
        }
        return $deleted;
    }

    /**
     * Get public URL — resolves from correct disk.
     */
    public function url(string $path): string
    {
        $disk = $this->currentDisk();
        try {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->url($path);
            }
        } catch (\Throwable $e) {}

        $other = $this->otherDisk($disk);
        try {
            if (Storage::disk($other)->exists($path)) {
                return Storage::disk($other)->url($path);
            }
        } catch (\Throwable $e) {}

        // Fallback: assume current disk
        return Storage::disk($disk)->url($path);
    }

    // ── Helpers ──────────────────────────────────────────

    private function fallbackDisk(): string
    {
        return config('storage_resilience.fallback_disk', 'local');
    }

    private function otherDisk(string $current): string
    {
        $primary  = config('storage_resilience.primary_disk', 'public');
        $fallback = config('storage_resilience.fallback_disk', 'local');
        return $current === $primary ? $fallback : $primary;
    }

    private function retryOnFallback(string $path, $contents, array $options): bool
    {
        $fallback = $this->fallbackDisk();
        if ($fallback === $this->currentDisk()) return false;

        try {
            $result = Storage::disk($fallback)->put($path, $contents, $options);
            if ($result) {
                StorageEvent::log('write_fallback_used', "File stored on fallback: {$path}", 'warning', $fallback);
            }
            return $result;
        } catch (\Throwable $e) {
            StorageEvent::log('write_failure', "Write also failed on fallback {$fallback}", 'critical', $fallback);
            return false;
        }
    }
}
