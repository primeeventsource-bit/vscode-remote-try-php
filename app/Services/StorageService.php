<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Unified storage service for all file uploads.
 * Handles avatars, chat icons, documents, chargeback files, import files.
 * Uses the configured disk (local in dev, azure/s3 in production).
 */
class StorageService
{
    private string $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.default', 'public');
    }

    // ═══════════════════════════════════════════════════════
    // UPLOAD
    // ═══════════════════════════════════════════════════════

    /**
     * Upload a file to storage.
     *
     * @param UploadedFile $file
     * @param string $directory Subdirectory (e.g., 'avatars', 'chat-icons', 'documents')
     * @param string|null $oldPath Previous file path to delete
     * @return string|null The stored path, or null on failure
     */
    public function upload(UploadedFile $file, string $directory, ?string $oldPath = null): ?string
    {
        // Validate
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file->getSize() > $maxSize) {
            Log::warning('StorageService: file too large', ['size' => $file->getSize(), 'max' => $maxSize]);
            return null;
        }

        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'application/pdf',
            'text/csv', 'text/plain',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
        ];

        if (!in_array($file->getMimeType(), $allowedMimes)) {
            Log::warning('StorageService: invalid mime type', ['mime' => $file->getMimeType()]);
            return null;
        }

        try {
            // Sanitize filename
            $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $ext = $file->getClientOriginalExtension() ?: 'bin';
            $filename = $name . '-' . Str::random(8) . '.' . $ext;

            $path = $file->storeAs($directory, $filename, $this->disk);

            // Delete old file if provided
            if ($oldPath) {
                $this->delete($oldPath);
            }

            return $path;
        } catch (\Throwable $e) {
            Log::error('StorageService::upload failed', [
                'directory' => $directory,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Upload a user avatar.
     */
    public function uploadAvatar(UploadedFile $file, ?string $oldPath = null): ?string
    {
        return $this->upload($file, 'avatars', $oldPath);
    }

    /**
     * Upload a group chat icon.
     */
    public function uploadChatIcon(UploadedFile $file, ?string $oldPath = null): ?string
    {
        return $this->upload($file, 'chat-icons', $oldPath);
    }

    // ═══════════════════════════════════════════════════════
    // URL GENERATION
    // ═══════════════════════════════════════════════════════

    /**
     * Get public URL for a stored file.
     */
    public function url(?string $path): string
    {
        if (!$path) return '';

        try {
            return Storage::disk($this->disk)->url($path);
        } catch (\Throwable $e) {
            // Fallback to asset helper
            return asset('storage/' . $path);
        }
    }

    // ═══════════════════════════════════════════════════════
    // DELETE
    // ═══════════════════════════════════════════════════════

    /**
     * Delete a file from storage.
     */
    public function delete(?string $path): bool
    {
        if (!$path) return false;

        try {
            return Storage::disk($this->disk)->delete($path);
        } catch (\Throwable $e) {
            Log::warning('StorageService::delete failed', ['path' => $path, 'error' => $e->getMessage()]);
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════
    // DIAGNOSTICS
    // ═══════════════════════════════════════════════════════

    /**
     * Run storage health check.
     */
    public function healthCheck(): array
    {
        $results = [
            'disk' => $this->disk,
            'write_test' => false,
            'read_test' => false,
            'url_test' => false,
            'error' => null,
        ];

        $testPath = '_health_check/test-' . Str::random(8) . '.txt';

        try {
            // Write test
            Storage::disk($this->disk)->put($testPath, 'health-check-' . now());
            $results['write_test'] = true;

            // Read test
            $content = Storage::disk($this->disk)->get($testPath);
            $results['read_test'] = str_starts_with($content, 'health-check-');

            // URL test
            $url = Storage::disk($this->disk)->url($testPath);
            $results['url_test'] = !empty($url);

            // Cleanup
            Storage::disk($this->disk)->delete($testPath);
        } catch (\Throwable $e) {
            $results['error'] = $e->getMessage();
        }

        return $results;
    }
}
