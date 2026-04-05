<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
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

    // ══════════════════════════════════════════════════════════════
    // CHARGED GREEN — FULFILLMENT TASK BATCH (ADMIN ONLY)
    // ══════════════════════════════════════════════════════════════

    /**
     * When a deal is charged green, create ALL fulfillment tasks
     * and send a DM alert to the assigned admin.
     *
     * HARD RULE: All tasks assigned to admin ONLY.
     *
     * Tasks created:
     * 1. Contact client to log in to app and website
     * 2. Send offers in 60 days from charged date
     * 3. Client needs to receive website clicks daily
     * 4. Follow-up call in 3-5 days from charged date
     */
    public static function onDealChargedGreen(int $dealId, string $clientName, User $admin): void
    {
        if (!self::isAdmin($admin)) return;

        $chargedDate = now();
        $base = [
            'created_by' => $admin->id,
            'client_name' => $clientName,
            'deal_id' => $dealId,
            'auto_created' => true,
            'related_type' => 'deal',
            'related_id' => $dealId,
        ];

        // Task 1: Contact client to log in
        self::createTask(array_merge($base, [
            'title' => "Contact {$clientName} — Client needs to log in to app and website",
            'type' => 'client_contact',
            'assigned_to' => $admin->id,
            'priority' => 'high',
            'due_date' => $chargedDate->copy()->addDay()->format('Y-m-d H:i'),
            'note' => 'Auto-created: Deal charged. Contact client to log in to app and website. If client shows no login, follow up immediately.',
            'metadata' => ['trigger' => 'charged_green', 'task_category' => 'login_contact'],
        ]));

        // Task 2: Send offers in 60 days
        self::createTask(array_merge($base, [
            'title' => "Send offers to {$clientName} — 60 days from closing/charged date",
            'type' => 'follow_up',
            'assigned_to' => $admin->id,
            'priority' => 'medium',
            'due_date' => $chargedDate->copy()->addDays(60)->format('Y-m-d H:i'),
            'note' => 'Auto-created: Client needs to be sent offers 60 days from closing/charged date.',
            'metadata' => ['trigger' => 'charged_green', 'task_category' => 'send_offers_60d'],
        ]));

        // Task 3: Website clicks daily
        self::createTask(array_merge($base, [
            'title' => "{$clientName} — Needs to receive website clicks daily",
            'type' => 'client_contact',
            'assigned_to' => $admin->id,
            'priority' => 'high',
            'due_date' => $chargedDate->copy()->addDay()->format('Y-m-d H:i'),
            'note' => 'Auto-created: Client needs to receive website clicks daily. Verify daily click activity is active.',
            'metadata' => ['trigger' => 'charged_green', 'task_category' => 'daily_website_clicks'],
        ]));

        // Task 4: Follow-up call in 3-5 days
        self::createTask(array_merge($base, [
            'title' => "Follow-up call to {$clientName} — 3-5 days from charged date",
            'type' => 'follow_up',
            'assigned_to' => $admin->id,
            'priority' => 'high',
            'due_date' => $chargedDate->copy()->addDays(3)->format('Y-m-d H:i'),
            'note' => 'Auto-created: Client needs to receive follow-up calls 3-5 days from charged date.',
            'metadata' => ['trigger' => 'charged_green', 'task_category' => 'followup_call_3_5d'],
        ]));

        // Also assign all 4 tasks to every other admin/master_admin
        $otherAdmins = User::whereIn('role', ['admin', 'master_admin'])
            ->where('id', '!=', $admin->id)
            ->pluck('id');

        foreach ($otherAdmins as $otherAdminId) {
            self::createTask(array_merge($base, [
                'title' => "[Shared] {$clientName} — Charged green fulfillment tasks",
                'type' => 'verification_action',
                'assigned_to' => $otherAdminId,
                'priority' => 'medium',
                'due_date' => $chargedDate->copy()->addDay()->format('Y-m-d H:i'),
                'note' => "Auto-created: Deal for {$clientName} was charged green by {$admin->name}. Review fulfillment tasks: login contact, 60-day offers, daily clicks, 3-5 day follow-up call.",
                'metadata' => ['trigger' => 'charged_green', 'task_category' => 'shared_fulfillment_summary'],
            ]));
        }

        // Send DM alert to the assigned admin on the deal
        self::sendChargedGreenAlert($dealId, $clientName, $admin);
    }

    /**
     * When deal moves to Verified status — admin-only task.
     */
    public static function onDealVerified(int $dealId, string $clientName, User $admin): void
    {
        if (!self::isAdmin($admin)) return;

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

    // ── DM Alert ────────────────────────────────────────────

    /**
     * Send a direct message alert to the admin on a charged deal
     * with the full task list summary.
     */
    private static function sendChargedGreenAlert(int $dealId, string $clientName, User $admin): void
    {
        try {
            // Use system user ID 0 or the admin themselves as sender
            $senderId = $admin->id;

            $chat = Chat::where('type', 'dm')->get()->first(function ($c) use ($senderId) {
                $m = is_array($c->members) ? $c->members : json_decode($c->members ?? '[]', true);
                $ids = array_map('intval', array_values($m));
                return in_array($senderId, $ids);
            });

            // Find or create a DM with the admin (self-DM for alerts)
            if (!$chat) {
                $chat = Chat::create([
                    'name' => 'Task Alerts',
                    'type' => 'dm',
                    'members' => [$senderId, $senderId],
                    'created_by' => $senderId,
                ]);
            }

            $text = "📋 CHARGED GREEN — Auto Tasks Created\n";
            $text .= "Client: {$clientName}\n";
            $text .= "Deal #{$dealId}\n\n";
            $text .= "The following fulfillment tasks have been auto-generated:\n\n";
            $text .= "1. Contact client to log in to app and website (Due: tomorrow)\n";
            $text .= "2. Send offers in 60 days from charged date\n";
            $text .= "3. Client needs to receive website clicks daily (Due: tomorrow)\n";
            $text .= "4. Follow-up call in 3-5 days from charged date\n\n";
            $text .= "All tasks are assigned to admin only.\n";
            $text .= "Open the Automatic Task List to manage these tasks.";

            Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $senderId,
                'message_type' => 'text',
                'text' => $text,
            ]);

            $chat->update(['updated_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Validates that a user is an admin.
     */
    private static function isAdmin(User $user): bool
    {
        return in_array($user->role, ['admin', 'master_admin', 'admin_limited']);
    }
}
