<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Unified settings service — replaces direct crm_settings queries.
 * Validates, persists, caches, and audits all settings changes.
 */
class SettingsService
{
    /**
     * Get a single setting.
     */
    public function get(string $category, string $key, mixed $default = null): mixed
    {
        return AppSetting::getValue($category, $key, $default);
    }

    /**
     * Get all settings for a category.
     */
    public function getCategory(string $category): array
    {
        return AppSetting::getCategory($category);
    }

    /**
     * Save a single setting.
     */
    public function set(string $category, string $key, mixed $value, ?int $userId = null): void
    {
        $old = $this->get($category, $key);
        AppSetting::setValue($category, $key, $value, $userId);
        $this->auditChange($category, $key, $old, $value, $userId);
    }

    /**
     * Save multiple settings atomically.
     */
    public function saveCategory(string $category, array $settings, ?int $userId = null): array
    {
        $errors = $this->validateSettings($category, $settings);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            DB::beginTransaction();

            foreach ($settings as $key => $value) {
                $old = $this->get($category, $key);
                AppSetting::setValue($category, $key, $value, $userId);
                $this->auditChange($category, $key, $old, $value, $userId);
            }

            DB::commit();

            // Clear relevant caches
            $this->clearCategoryCache($category);

            return ['success' => true, 'errors' => []];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('SettingsService::saveCategory failed', [
                'category' => $category,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'errors' => ['save' => 'Failed to save settings: ' . $e->getMessage()]];
        }
    }

    /**
     * Validate settings before saving.
     */
    private function validateSettings(string $category, array $settings): array
    {
        $errors = [];

        foreach ($settings as $key => $value) {
            // Type-specific validation
            if ($key === 'module_enabled' && !is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false], true)) {
                $errors[$key] = 'Must be true or false';
            }
        }

        return $errors;
    }

    /**
     * Audit a setting change.
     */
    private function auditChange(string $category, string $key, mixed $old, mixed $new, ?int $userId): void
    {
        if ($old === $new) return;

        try {
            DB::table('audit_logs')->insert([
                'user_id' => $userId,
                'action' => 'settings.update',
                'target_type' => 'app_setting',
                'target_id' => null,
                'old_values' => json_encode(['category' => $category, 'key' => $key, 'value' => $old]),
                'new_values' => json_encode(['category' => $category, 'key' => $key, 'value' => $new]),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit failure should not block settings save
        }
    }

    /**
     * Clear caches for a category.
     */
    private function clearCategoryCache(string $category): void
    {
        try {
            // Clear all settings in this category from cache
            $settings = AppSetting::where('category', $category)->get();
            foreach ($settings as $setting) {
                Cache::forget("app_setting.{$category}.{$setting->key}");
            }

            // If config-affecting settings changed, clear config cache
            if (in_array($category, ['general', 'twilio', 'storage', 'chat'])) {
                try { Artisan::call('config:clear'); } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Check if a module is enabled.
     */
    public function isModuleEnabled(string $module): bool
    {
        return (bool) $this->get($module, 'module_enabled', true);
    }
}
