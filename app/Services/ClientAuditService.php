<?php

namespace App\Services;

use App\Models\ClientAuditLog;
use App\Models\Deal;
use App\Models\User;

class ClientAuditService
{
    /**
     * Sensitive fields whose values are masked in audit logs.
     * We store the fact that they changed, but not the raw value.
     */
    private const MASKED_FIELDS = [
        'card_number', 'card_number2', 'exp_date', 'exp_date2',
        'card_last4', 'card_last4_2',
    ];

    /**
     * Log a view action for a specific section.
     */
    public static function logView(User $user, Deal $deal, string $section): void
    {
        self::write($user, $deal, "viewed_{$section}", $section);
    }

    /**
     * Log an edit action, capturing before/after values.
     */
    public static function logEdit(
        User $user,
        Deal $deal,
        string $section,
        array $changedFields,
        array $beforeValues,
        array $afterValues
    ): void {
        // Mask sensitive fields in before/after
        $safeBefore = self::maskSensitive($beforeValues);
        $safeAfter = self::maskSensitive($afterValues);

        self::write($user, $deal, "edited_{$section}", $section, $changedFields, $safeBefore, $safeAfter);
    }

    private static function write(
        User $user,
        Deal $deal,
        string $action,
        string $section,
        ?array $changedFields = null,
        ?array $before = null,
        ?array $after = null
    ): void {
        try {
            ClientAuditLog::create([
                'user_id' => $user->id,
                'user_role' => $user->role,
                'deal_id' => $deal->id,
                'action' => $action,
                'section' => $section,
                'changed_fields' => $changedFields,
                'before_values' => $before,
                'after_values' => $after,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit logging must never break the main operation
            report($e);
        }
    }

    private static function maskSensitive(array $values): array
    {
        foreach (self::MASKED_FIELDS as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = '***MASKED***';
            }
        }
        return $values;
    }
}
