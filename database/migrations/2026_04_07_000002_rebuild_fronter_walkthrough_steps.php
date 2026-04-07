<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the generic fronter onboarding steps with 6 precise interactive
 * walkthrough steps that highlight real CRM UI elements.
 *
 * Steps:
 *   1. Dashboard overview   – tooltip on fronter stats panel
 *   2. Open Leads tab       – action: click the Leads nav item
 *   3. Click a lead         – action: click a lead row in the table
 *   4. Add notes / edit     – action: click Edit Lead button
 *   5. Change status        – tooltip on disposition buttons
 *   6. Transfer lead        – tooltip on transfer-to-closer dropdown
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('onboarding_flows') || !Schema::hasTable('onboarding_steps')) {
            return;
        }

        // Find the fronter flow
        $flow = DB::table('onboarding_flows')->where('role', 'fronter')->where('is_active', true)->first();
        if (!$flow) return;

        // Delete existing fronter steps
        DB::table('onboarding_steps')->where('flow_id', $flow->id)->delete();

        // Also clear any user progress for this flow so users get the new walkthrough
        if (Schema::hasTable('user_onboarding_progress')) {
            DB::table('user_onboarding_progress')->where('flow_id', $flow->id)->delete();
        }
        if (Schema::hasTable('training_completion_summary')) {
            DB::table('training_completion_summary')->where('flow_id', $flow->id)->delete();
        }

        // Update flow metadata
        DB::table('onboarding_flows')->where('id', $flow->id)->update([
            'name' => 'Fronter Training Walkthrough',
            'description' => 'Interactive step-by-step guide that walks you through your daily workflow — from your dashboard to transferring qualified leads.',
            'updated_at' => now(),
        ]);

        // ────────────────────────────────────────────────
        // Insert the 6 interactive steps
        // ────────────────────────────────────────────────

        $steps = [
            // ── STEP 1: Dashboard Overview ──────────────
            [
                'flow_id'           => $flow->id,
                'key'               => 'dashboard_overview',
                'title'             => 'Your Dashboard',
                'description'       => 'This is your home base. You can see your transfers sent, deals closed, close rate, and no-deal percentage here. Check these numbers at the start of every shift so you know where you stand.',
                'step_type'         => 'tooltip',
                'target_route'      => '/dashboard',
                'target_selector'   => '[data-training="fronter-stats"]',
                'action_event'      => null,
                'action_value'      => null,
                'tooltip_position'  => 'bottom',
                'icon'              => '📊',
                'help_link'         => null,
                'image_path'        => null,
                'image_caption'     => null,
                'tip_text'          => 'Your Close % = deals closed from your transfers. Aim for the highest close rate on the board.',
                'sort_order'        => 0,
                'is_required'       => true,
                'is_enabled'        => true,
                'highlight_element' => true,
                'dim_background'    => true,
                'auto_scroll'       => true,
                'created_at'        => now(),
                'updated_at'        => now(),
            ],

            // ── STEP 2: Open the Leads Tab ──────────────
            [
                'flow_id'           => $flow->id,
                'key'               => 'open_leads_tab',
                'title'             => 'Open the Leads Page',
                'description'       => 'Click the Leads tab in the sidebar to see all leads assigned to you. This is where you will spend most of your time — calling, qualifying, and transferring leads.',
                'step_type'         => 'action',
                'target_route'      => '/dashboard',
                'target_selector'   => '[data-training="nav-leads"]',
                'action_event'      => 'click',
                'action_value'      => '/leads',
                'tooltip_position'  => 'right',
                'icon'              => '📋',
                'help_link'         => null,
                'image_path'        => null,
                'image_caption'     => null,
                'tip_text'          => 'Open the sidebar menu first (hamburger icon top-left), then click Leads.',
                'sort_order'        => 1,
                'is_required'       => true,
                'is_enabled'        => true,
                'highlight_element' => true,
                'dim_background'    => true,
                'auto_scroll'       => true,
                'created_at'        => now(),
                'updated_at'        => now(),
            ],

            // ── STEP 3: Click a Lead ────────────────────
            [
                'flow_id'           => $flow->id,
                'key'               => 'click_a_lead',
                'title'             => 'Click a Lead to Open It',
                'description'       => 'Click any lead row in the table to open its detail panel. You will see the owner name, phone numbers, resort, location, and current disposition. Always review all the data before calling.',
                'step_type'         => 'action',
                'target_route'      => '/leads',
                'target_selector'   => '[data-training="lead-row"]',
                'action_event'      => 'click',
                'action_value'      => null,
                'tooltip_position'  => 'bottom',
                'icon'              => '👆',
                'help_link'         => null,
                'image_path'        => null,
                'image_caption'     => null,
                'tip_text'          => 'Click any row — the detail panel will open below the table showing all lead info.',
                'sort_order'        => 2,
                'is_required'       => true,
                'is_enabled'        => true,
                'highlight_element' => true,
                'dim_background'    => true,
                'auto_scroll'       => true,
                'created_at'        => now(),
                'updated_at'        => now(),
            ],

            // ── STEP 4: Edit Lead / Add Notes ───────────
            [
                'flow_id'           => $flow->id,
                'key'               => 'edit_lead_notes',
                'title'             => 'Edit the Lead and Add Notes',
                'description'       => 'Click "Edit Lead" to update contact info and add notes. Every lead MUST have notes before transferring. Write what the owner said, their interest level, and any objections. Good notes help closers close deals.',
                'step_type'         => 'action',
                'target_route'      => '/leads',
                'target_selector'   => '[data-training="edit-lead-btn"]',
                'action_event'      => 'click',
                'action_value'      => null,
                'tooltip_position'  => 'top',
                'icon'              => '✏️',
                'help_link'         => null,
                'image_path'        => null,
                'image_caption'     => null,
                'tip_text'          => 'Good note example: "Spoke with John, owns 2BR at Marriott Orlando, wants to exit, owes $8k in fees, very interested, wife also on board." Bad note: "called, interested".',
                'sort_order'        => 3,
                'is_required'       => true,
                'is_enabled'        => true,
                'highlight_element' => true,
                'dim_background'    => true,
                'auto_scroll'       => true,
                'created_at'        => now(),
                'updated_at'        => now(),
            ],

            // ── STEP 5: Change Lead Status ──────────────
            [
                'flow_id'           => $flow->id,
                'key'               => 'change_status',
                'title'             => 'Set the Lead Disposition',
                'description'       => 'After each call, set the correct status. Use "Right Number" if you confirmed it is the owner. Use "Wrong Number" or "Disconnected" if the number does not work. Use "Callback" if they asked you to call back later. Use "Left Voice Mail" if no answer.',
                'step_type'         => 'tooltip',
                'target_route'      => '/leads',
                'target_selector'   => '[data-training="disposition-buttons"]',
                'action_event'      => null,
                'action_value'      => null,
                'tooltip_position'  => 'top',
                'icon'              => '🏷️',
                'help_link'         => null,
                'image_path'        => null,
                'image_caption'     => null,
                'tip_text'          => 'Always set a disposition after every call. Never leave a lead undisposed — your manager tracks undisposed counts.',
                'sort_order'        => 4,
                'is_required'       => true,
                'is_enabled'        => true,
                'highlight_element' => true,
                'dim_background'    => true,
                'auto_scroll'       => true,
                'created_at'        => now(),
                'updated_at'        => now(),
            ],

            // ── STEP 6: Transfer Lead ───────────────────
            [
                'flow_id'           => $flow->id,
                'key'               => 'transfer_lead',
                'title'             => 'Transfer a Qualified Lead',
                'description'       => 'When a lead is qualified — confirmed owner, interested, and has good notes — select a user from the "Transfer" dropdown and click "Transfer Lead". The lead moves to their queue and your transfer count goes up.',
                'step_type'         => 'tooltip',
                'target_route'      => '/leads',
                'target_selector'   => '[data-training="transfer-closer"]',
                'action_event'      => null,
                'action_value'      => null,
                'tooltip_position'  => 'top',
                'icon'              => '↗️',
                'help_link'         => null,
                'image_path'        => null,
                'image_caption'     => null,
                'tip_text'          => 'Do NOT transfer leads without notes and qualification. Unqualified transfers hurt your stats and waste closer time.',
                'sort_order'        => 5,
                'is_required'       => true,
                'is_enabled'        => true,
                'highlight_element' => true,
                'dim_background'    => true,
                'auto_scroll'       => true,
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
        ];

        DB::table('onboarding_steps')->insert($steps);
    }

    public function down(): void
    {
        // No rollback — the original seed migration can re-run if needed
    }
};
