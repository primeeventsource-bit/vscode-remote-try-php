<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backfills pipeline_events from existing leads and deals data.
 * This is idempotent - safe to run multiple times.
 */
return new class extends Migration
{
    public function up(): void
    {
        $stats = ['transfers' => 0, 'deals_created' => 0, 'verifications' => 0, 'charges' => 0];

        // 1. Backfill transferred_to_closer events from leads
        $transferred = DB::table('leads')
            ->where('disposition', 'Transferred to Closer')
            ->whereNotNull('transferred_to')
            ->get();

        foreach ($transferred as $lead) {
            $exists = DB::table('pipeline_events')
                ->where('lead_id', $lead->id)
                ->where('event_type', 'transferred_to_closer')
                ->exists();
            if ($exists) continue;

            DB::table('pipeline_events')->insert([
                'lead_id' => $lead->id,
                'event_type' => 'transferred_to_closer',
                'from_stage' => 'fronter_working',
                'to_stage' => 'transferred_to_closer',
                'performed_by_user_id' => $lead->original_fronter ?? $lead->assigned_to,
                'source_user_id' => $lead->original_fronter ?? $lead->assigned_to,
                'target_user_id' => is_numeric($lead->transferred_to) ? (int) $lead->transferred_to : null,
                'source_role' => 'fronter',
                'target_role' => 'closer',
                'success_flag' => true,
                'event_at' => $lead->updated_at ?? $lead->created_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $stats['transfers']++;
        }

        // 2. Backfill closer_closed_deal events from deals
        $deals = DB::table('deals')->whereNotNull('closer')->get();

        foreach ($deals as $deal) {
            // Try to link deal to lead via owner_name match
            if (!$deal->lead_id) {
                $lead = DB::table('leads')
                    ->where('owner_name', $deal->owner_name)
                    ->where('disposition', 'Converted to Deal')
                    ->first();

                if ($lead) {
                    DB::table('deals')->where('id', $deal->id)->update(['lead_id' => $lead->id]);
                    $deal->lead_id = $lead->id;
                }
            }

            $exists = DB::table('pipeline_events')
                ->where('deal_id', $deal->id)
                ->where('event_type', 'closer_closed_deal')
                ->exists();
            if ($exists) continue;

            DB::table('pipeline_events')->insert([
                'lead_id' => $deal->lead_id,
                'deal_id' => $deal->id,
                'event_type' => 'closer_closed_deal',
                'from_stage' => 'transferred_to_closer',
                'to_stage' => 'closed_deal',
                'performed_by_user_id' => $deal->closer,
                'source_user_id' => $deal->closer,
                'source_role' => 'closer',
                'success_flag' => true,
                'event_at' => $deal->created_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $stats['deals_created']++;

            // 3. Backfill sent_to_verification if deal has assigned_admin
            if ($deal->assigned_admin && in_array($deal->status, ['in_verification', 'charged', 'chargeback', 'chargeback_won', 'chargeback_lost'])) {
                $vExists = DB::table('pipeline_events')
                    ->where('deal_id', $deal->id)
                    ->where('event_type', 'sent_to_verification')
                    ->exists();
                if (!$vExists) {
                    DB::table('pipeline_events')->insert([
                        'lead_id' => $deal->lead_id,
                        'deal_id' => $deal->id,
                        'event_type' => 'sent_to_verification',
                        'from_stage' => 'closed_deal',
                        'to_stage' => 'sent_to_verification',
                        'performed_by_user_id' => $deal->closer,
                        'source_user_id' => $deal->closer,
                        'target_user_id' => $deal->assigned_admin,
                        'source_role' => 'closer',
                        'target_role' => 'admin',
                        'success_flag' => true,
                        'event_at' => $deal->created_at ?? now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $stats['verifications']++;
                }
            }

            // 4. Backfill verification_charged_green if deal is charged
            if ($deal->charged === 'yes' && $deal->assigned_admin) {
                $cExists = DB::table('pipeline_events')
                    ->where('deal_id', $deal->id)
                    ->where('event_type', 'verification_charged_green')
                    ->exists();
                if (!$cExists) {
                    DB::table('pipeline_events')->insert([
                        'lead_id' => $deal->lead_id,
                        'deal_id' => $deal->id,
                        'event_type' => 'verification_charged_green',
                        'from_stage' => 'sent_to_verification',
                        'to_stage' => 'charged_green',
                        'performed_by_user_id' => $deal->assigned_admin,
                        'target_user_id' => $deal->assigned_admin,
                        'target_role' => 'admin',
                        'success_flag' => true,
                        'outcome' => 'charged',
                        'event_at' => $deal->charged_date ?? $deal->updated_at ?? now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $stats['charges']++;
                }
            }

            // 5. Backfill verification_not_charged for cancelled deals with admin
            if ($deal->status === 'cancelled' && $deal->assigned_admin) {
                $ncExists = DB::table('pipeline_events')
                    ->where('deal_id', $deal->id)
                    ->where('event_type', 'verification_not_charged')
                    ->exists();
                if (!$ncExists) {
                    DB::table('pipeline_events')->insert([
                        'lead_id' => $deal->lead_id,
                        'deal_id' => $deal->id,
                        'event_type' => 'verification_not_charged',
                        'from_stage' => 'sent_to_verification',
                        'to_stage' => 'not_charged',
                        'performed_by_user_id' => $deal->assigned_admin,
                        'target_user_id' => $deal->assigned_admin,
                        'target_role' => 'admin',
                        'success_flag' => false,
                        'outcome' => 'not_charged',
                        'event_at' => $deal->updated_at ?? now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        Log::info('Pipeline events backfill complete', $stats);
    }

    public function down(): void
    {
        // Backfill data can be safely removed
        DB::table('pipeline_events')->delete();
    }
};
