<?php

namespace App\Console\Commands;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillChargedDealTasks extends Command
{
    protected $signature = 'tasks:backfill-charged';
    protected $description = 'Create fulfillment tasks for all existing charged deals that have no auto-tasks yet';

    public function handle(): int
    {
        $chargedDeals = Deal::where('charged', 'yes')
            ->whereIn('status', ['charged', 'chargeback', 'chargeback_won', 'chargeback_lost'])
            ->get();

        $this->info("Found {$chargedDeals->count()} charged deals.");

        $created = 0;

        foreach ($chargedDeals as $deal) {
            // Skip if tasks already exist for this deal
            $existing = DB::table('tasks')
                ->where('deal_id', $deal->id)
                ->where('type', 'client_contact')
                ->count();

            if ($existing > 0) {
                $this->line("  Skip Deal #{$deal->id} ({$deal->owner_name}) — tasks already exist");
                continue;
            }

            // Find the admin on this deal
            $adminId = $deal->assigned_admin;
            $admin = $adminId ? User::find($adminId) : null;

            // If no admin, assign to first master_admin
            if (!$admin || !in_array($admin->role, ['admin', 'master_admin', 'admin_limited'])) {
                $admin = User::where('role', 'master_admin')->first();
            }

            if (!$admin) {
                $this->warn("  Skip Deal #{$deal->id} — no admin found");
                continue;
            }

            $clientName = $deal->owner_name ?? 'Client';
            $chargedDate = $deal->charged_date ? \Carbon\Carbon::parse($deal->charged_date) : ($deal->created_at ?? now());

            $base = [
                'created_by' => $admin->id,
                'client_name' => $clientName,
                'deal_id' => $deal->id,
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $autoFields = [];
            if (\Schema::hasColumn('tasks', 'auto_created')) {
                $autoFields = [
                    'auto_created' => true,
                    'related_type' => 'deal',
                    'related_id' => $deal->id,
                ];
            }

            // Task 1: Contact client to log in
            DB::table('tasks')->insert(array_merge($base, $autoFields, [
                'title' => "Contact {$clientName} — Client needs to log in to app and website",
                'type' => 'client_contact',
                'assigned_to' => $admin->id,
                'priority' => 'high',
                'due_date' => $chargedDate->copy()->addDay()->format('Y-m-d H:i'),
                'notes' => json_encode([['text' => 'Backfilled: Deal was charged. Contact client to log in to app and website.', 'by' => $admin->id, 'time' => now()->format('M j, Y - g:i A')]]),
            ]));

            // Task 2: Send offers in 60 days
            DB::table('tasks')->insert(array_merge($base, $autoFields, [
                'title' => "Send offers to {$clientName} — 60 days from closing/charged date",
                'type' => 'follow_up',
                'assigned_to' => $admin->id,
                'priority' => 'medium',
                'due_date' => $chargedDate->copy()->addDays(60)->format('Y-m-d H:i'),
                'notes' => json_encode([['text' => 'Backfilled: Client needs to be sent offers 60 days from closing/charged date.', 'by' => $admin->id, 'time' => now()->format('M j, Y - g:i A')]]),
            ]));

            // Task 3: Website clicks daily
            DB::table('tasks')->insert(array_merge($base, $autoFields, [
                'title' => "{$clientName} — Needs to receive website clicks daily",
                'type' => 'client_contact',
                'assigned_to' => $admin->id,
                'priority' => 'high',
                'due_date' => $chargedDate->copy()->addDay()->format('Y-m-d H:i'),
                'notes' => json_encode([['text' => 'Backfilled: Client needs to receive website clicks daily.', 'by' => $admin->id, 'time' => now()->format('M j, Y - g:i A')]]),
            ]));

            // Task 4: Follow-up call in 3-5 days
            DB::table('tasks')->insert(array_merge($base, $autoFields, [
                'title' => "Follow-up call to {$clientName} — 3-5 days from charged date",
                'type' => 'follow_up',
                'assigned_to' => $admin->id,
                'priority' => 'high',
                'due_date' => $chargedDate->copy()->addDays(3)->format('Y-m-d H:i'),
                'notes' => json_encode([['text' => 'Backfilled: Client needs follow-up calls 3-5 days from charged date.', 'by' => $admin->id, 'time' => now()->format('M j, Y - g:i A')]]),
            ]));

            $created += 4;
            $this->info("  Deal #{$deal->id} ({$clientName}) — 4 tasks created, assigned to {$admin->name}");
        }

        $this->info("Done. Created {$created} tasks total.");
        return 0;
    }
}
