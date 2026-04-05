<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates automatic tasks from CRM workflow events.
 * All methods are safe to call even if migration hasn't run.
 */
class AutomaticTaskService
{
    private static function ready(): bool
    {
        return Schema::hasColumn('tasks', 'auto_created');
    }

    public static function createTask(array $data): void
    {
        try {
            $row = [
                'title' => $data['title'],
                'type' => $data['type'] ?? 'general',
                'description' => $data['description'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'created_by' => $data['created_by'] ?? null,
                'client_name' => $data['client_name'] ?? null,
                'status' => 'open',
                'priority' => $data['priority'] ?? 'medium',
                'due_date' => $data['due_date'] ?? null,
                'deal_id' => $data['deal_id'] ?? null,
                'lead_id' => $data['lead_id'] ?? null,
                'notes' => json_encode([['text' => $data['note'] ?? 'Auto-created task', 'by' => $data['created_by'] ?? 0, 'time' => now()->format('M j, Y - g:i A')]]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (self::ready()) {
                $row['auto_created'] = $data['auto_created'] ?? true;
                $row['related_type'] = $data['related_type'] ?? null;
                $row['related_id'] = $data['related_id'] ?? null;
                $row['metadata'] = isset($data['metadata']) ? json_encode($data['metadata']) : null;
            }

            DB::table('tasks')->insert($row);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ── Workflow triggers ────────────────────────────────────

    public static function onLeadTransferred(int $leadId, string $leadName, int $closerId, int $fronterId): void
    {
        self::createTask([
            'title' => "Follow up on transferred lead: {$leadName}",
            'type' => 'transfer_followup',
            'assigned_to' => $closerId,
            'created_by' => $fronterId,
            'client_name' => $leadName,
            'lead_id' => $leadId,
            'priority' => 'medium',
            'due_date' => now()->addDay()->format('Y-m-d H:i'),
            'auto_created' => true,
            'related_type' => 'lead',
            'related_id' => $leadId,
            'note' => 'Auto-created: Lead transferred to you for follow-up.',
        ]);
    }

    public static function onDealSentToVerification(int $dealId, string $clientName, int $adminId, int $closerId): void
    {
        self::createTask([
            'title' => "Verify deal: {$clientName}",
            'type' => 'verification_action',
            'assigned_to' => $adminId,
            'created_by' => $closerId,
            'client_name' => $clientName,
            'deal_id' => $dealId,
            'priority' => 'high',
            'due_date' => now()->addDay()->format('Y-m-d H:i'),
            'auto_created' => true,
            'related_type' => 'deal',
            'related_id' => $dealId,
            'note' => 'Auto-created: Deal sent to verification. Review and process.',
        ]);
    }

    public static function onChargebackCaseCreated(int $caseId, string $caseNumber, int $clientId, string $clientName, int $createdBy): void
    {
        self::createTask([
            'title' => "Chargeback case {$caseNumber}: Gather evidence for {$clientName}",
            'type' => 'missing_evidence',
            'assigned_to' => $createdBy,
            'created_by' => $createdBy,
            'client_name' => $clientName,
            'deal_id' => $clientId,
            'priority' => 'urgent',
            'due_date' => now()->addDays(7)->format('Y-m-d H:i'),
            'auto_created' => true,
            'related_type' => 'chargeback_case',
            'related_id' => $caseId,
            'note' => "Auto-created: Upload all required evidence for chargeback case {$caseNumber}.",
        ]);
    }

    public static function onChargebackDeadlineApproaching(int $caseId, string $caseNumber, string $deadline, int $assignedTo): void
    {
        self::createTask([
            'title' => "URGENT: Chargeback deadline approaching — {$caseNumber}",
            'type' => 'chargeback_deadline',
            'assigned_to' => $assignedTo,
            'created_by' => null,
            'priority' => 'urgent',
            'due_date' => $deadline,
            'auto_created' => true,
            'related_type' => 'chargeback_case',
            'related_id' => $caseId,
            'note' => "Auto-created: Response deadline for {$caseNumber} is {$deadline}. Ensure evidence package is complete.",
        ]);
    }

    public static function onNoteSharedUrgent(int $noteId, string $clientName, int $recipientId, int $senderId): void
    {
        self::createTask([
            'title' => "Urgent note shared: Review {$clientName}",
            'type' => 'internal_review',
            'assigned_to' => $recipientId,
            'created_by' => $senderId,
            'client_name' => $clientName,
            'priority' => 'high',
            'auto_created' => true,
            'related_type' => 'note',
            'related_id' => $noteId,
            'note' => 'Auto-created: A note was shared with you for urgent follow-up.',
        ]);
    }

    // ── ADMIN-ONLY auto-tasks for Verified / Charged Green ──

    /**
     * Auto-task when deal moves to Verified status.
     * HARD RULE: assigned to admin ONLY.
     */
    public static function onDealVerified(int $dealId, string $clientName, \App\Models\User $admin): void
    {
        if (!self::isAdmin($admin)) return; // reject non-admin

        self::createTask([
            'title' => "Deal verified: Process {$clientName}",
            'type' => 'verification_action',
            'assigned_to' => $admin->id,
            'created_by' => $admin->id,
            'client_name' => $clientName,
            'deal_id' => $dealId,
            'priority' => 'high',
            'due_date' => now()->addDay()->format('Y-m-d H:i'),
            'auto_created' => true,
            'related_type' => 'deal',
            'related_id' => $dealId,
            'note' => 'Auto-created (admin-only): Deal verified. Complete charging process.',
            'metadata' => ['assignee_mode' => 'admin_only', 'trigger' => 'verified'],
        ]);
    }

    /**
     * Auto-task when deal is Charged Green.
     * HARD RULE: assigned to admin ONLY.
     */
    public static function onDealChargedGreen(int $dealId, string $clientName, \App\Models\User $admin): void
    {
        if (!self::isAdmin($admin)) return; // reject non-admin

        self::createTask([
            'title' => "Deal charged green: Finalize {$clientName}",
            'type' => 'verification_action',
            'assigned_to' => $admin->id,
            'created_by' => $admin->id,
            'client_name' => $clientName,
            'deal_id' => $dealId,
            'priority' => 'medium',
            'due_date' => now()->addDay()->format('Y-m-d H:i'),
            'auto_created' => true,
            'related_type' => 'deal',
            'related_id' => $dealId,
            'note' => 'Auto-created (admin-only): Deal charged green. Complete post-charge tasks and payroll.',
            'metadata' => ['assignee_mode' => 'admin_only', 'trigger' => 'charged_green'],
        ]);
    }

    /**
     * Validates that a user is an admin. Used to enforce admin-only assignment.
     */
    private static function isAdmin(\App\Models\User $user): bool
    {
        return in_array($user->role, ['admin', 'master_admin', 'admin_limited']);
    }
}
