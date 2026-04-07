<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Enhance onboarding_flows with guide-builder fields ──
        if (Schema::hasTable('onboarding_flows')) {
            $cols = Schema::getColumnListing('onboarding_flows');

            if (!in_array('created_by', $cols)) {
                Schema::table('onboarding_flows', function (Blueprint $table) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('version');
                    $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                    $table->boolean('is_published')->default(true)->after('is_active');
                    $table->boolean('auto_start_on_first_login')->default(true)->after('is_published');
                    $table->boolean('allow_skip')->default(true)->after('auto_start_on_first_login');
                    $table->boolean('lock_ui_during_training')->default(false)->after('allow_skip');
                    $table->string('cover_image_path')->nullable()->after('description');
                });
            }
        }

        // ── Enhance onboarding_steps with interactive walkthrough fields ──
        if (Schema::hasTable('onboarding_steps')) {
            $cols = Schema::getColumnListing('onboarding_steps');

            if (!in_array('step_type', $cols)) {
                Schema::table('onboarding_steps', function (Blueprint $table) {
                    $table->string('step_type', 30)->default('tooltip')->after('description');
                    // tooltip, action, info, screenshot
                    $table->string('action_event', 100)->nullable()->after('target_selector');
                    // e.g. 'click', 'input', 'navigate' — what user must do
                    $table->string('action_value', 255)->nullable()->after('action_event');
                    // e.g. route name or selector to click
                    $table->string('tooltip_position', 20)->default('bottom')->after('action_value');
                    // top, bottom, left, right
                    $table->string('image_path')->nullable()->after('help_link');
                    $table->string('image_caption')->nullable()->after('image_path');
                    $table->text('tip_text')->nullable()->after('image_caption');
                    $table->boolean('is_enabled')->default(true)->after('is_required');
                    $table->boolean('highlight_element')->default(true)->after('is_enabled');
                    $table->boolean('dim_background')->default(true)->after('highlight_element');
                    $table->boolean('auto_scroll')->default(true)->after('dim_background');
                });
            }
        }

        // ── Enhance user_onboarding_progress with richer tracking ──
        if (Schema::hasTable('user_onboarding_progress')) {
            $cols = Schema::getColumnListing('user_onboarding_progress');

            if (!in_array('started_at', $cols)) {
                Schema::table('user_onboarding_progress', function (Blueprint $table) {
                    $table->timestamp('started_at')->nullable()->after('status');
                    $table->timestamp('last_viewed_at')->nullable()->after('skipped_at');
                });
            }
        }

        // ── Step images table (multiple images per step) ──
        if (!Schema::hasTable('onboarding_step_images')) {
            Schema::create('onboarding_step_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('step_id')->constrained('onboarding_steps')->cascadeOnDelete();
                $table->string('image_path');
                $table->string('caption')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index(['step_id', 'sort_order']);
            });
        }

        // ── Training guide completion summary (per-user per-flow) ──
        if (!Schema::hasTable('training_completion_summary')) {
            Schema::create('training_completion_summary', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('flow_id')->constrained('onboarding_flows')->cascadeOnDelete();
                $table->unsignedBigInteger('current_step_id')->nullable();
                $table->integer('progress_percent')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('last_viewed_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'flow_id']);
                $table->index('progress_percent');
            });
        }

        // ── Update existing steps with step_type and selector data ──
        self::updateExistingSteps();
    }

    private static function updateExistingSteps(): void
    {
        if (!Schema::hasTable('onboarding_steps')) return;
        if (!Schema::hasColumn('onboarding_steps', 'step_type')) return;

        // Map existing step keys to interactive selectors and types
        $selectorMap = [
            'welcome'          => ['step_type' => 'info',    'target_selector' => null, 'tooltip_position' => 'bottom'],
            'dashboard'        => ['step_type' => 'tooltip', 'target_selector' => '[data-training="nav-dashboard"]', 'tooltip_position' => 'right'],
            'leads'            => ['step_type' => 'action',  'target_selector' => '[data-training="nav-leads"]', 'tooltip_position' => 'right', 'action_event' => 'click'],
            'edit_lead'        => ['step_type' => 'tooltip', 'target_selector' => '[data-training="lead-row"]', 'tooltip_position' => 'bottom'],
            'transfer'         => ['step_type' => 'tooltip', 'target_selector' => '[data-training="transfer-closer"]', 'tooltip_position' => 'top'],
            'chat'             => ['step_type' => 'action',  'target_selector' => '[data-training="nav-chat"]', 'tooltip_position' => 'right', 'action_event' => 'click'],
            'profile'          => ['step_type' => 'tooltip', 'target_selector' => '[data-training="nav-settings"]', 'tooltip_position' => 'right'],
            'stats'            => ['step_type' => 'tooltip', 'target_selector' => '[data-training="nav-stats"]', 'tooltip_position' => 'right'],
            'deals'            => ['step_type' => 'action',  'target_selector' => '[data-training="nav-deals"]', 'tooltip_position' => 'right', 'action_event' => 'click'],
            'create_deal'      => ['step_type' => 'tooltip', 'target_selector' => '[data-training="convert-deal-btn"]', 'tooltip_position' => 'top'],
            'verification'     => ['step_type' => 'tooltip', 'target_selector' => '[data-training="nav-verification"]', 'tooltip_position' => 'right'],
            'payroll'          => ['step_type' => 'tooltip', 'target_selector' => '[data-training="nav-payroll"]', 'tooltip_position' => 'right'],
            'transfer_closer'  => ['step_type' => 'tooltip', 'target_selector' => '[data-training="transfer-closer"]', 'tooltip_position' => 'top'],
            'clients'          => ['step_type' => 'tooltip', 'target_selector' => '[data-training="nav-clients"]', 'tooltip_position' => 'right'],
            'chargebacks'      => ['step_type' => 'tooltip', 'target_selector' => '[data-training="nav-chargebacks"]', 'tooltip_position' => 'right'],
            'users'            => ['step_type' => 'action',  'target_selector' => '[data-training="nav-users"]', 'tooltip_position' => 'right', 'action_event' => 'click'],
            'documents'        => ['step_type' => 'tooltip', 'target_selector' => '[data-training="nav-documents"]', 'tooltip_position' => 'right'],
            'settings'         => ['step_type' => 'tooltip', 'target_selector' => '[data-training="nav-settings"]', 'tooltip_position' => 'right'],
            'security'         => ['step_type' => 'info',    'target_selector' => null, 'tooltip_position' => 'bottom'],
            'notes'            => ['step_type' => 'tooltip', 'target_selector' => '[data-training="nav-clients"]', 'tooltip_position' => 'right'],
            'complete'         => ['step_type' => 'info',    'target_selector' => null, 'tooltip_position' => 'bottom'],
        ];

        foreach ($selectorMap as $key => $data) {
            $update = [
                'step_type' => $data['step_type'],
                'tooltip_position' => $data['tooltip_position'],
                'highlight_element' => $data['target_selector'] ? true : false,
                'dim_background' => true,
                'auto_scroll' => true,
                'is_enabled' => true,
            ];
            if (isset($data['target_selector'])) {
                $update['target_selector'] = $data['target_selector'];
            }
            if (isset($data['action_event'])) {
                $update['action_event'] = $data['action_event'];
            }

            DB::table('onboarding_steps')->where('key', $key)->update($update);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('training_completion_summary');
        Schema::dropIfExists('onboarding_step_images');

        if (Schema::hasTable('onboarding_flows')) {
            Schema::table('onboarding_flows', function (Blueprint $table) {
                $cols = Schema::getColumnListing('onboarding_flows');
                $drop = array_intersect($cols, ['created_by', 'updated_by', 'is_published', 'auto_start_on_first_login', 'allow_skip', 'lock_ui_during_training', 'cover_image_path']);
                if ($drop) $table->dropColumn($drop);
            });
        }

        if (Schema::hasTable('onboarding_steps')) {
            Schema::table('onboarding_steps', function (Blueprint $table) {
                $cols = Schema::getColumnListing('onboarding_steps');
                $drop = array_intersect($cols, ['step_type', 'action_event', 'action_value', 'tooltip_position', 'image_path', 'image_caption', 'tip_text', 'is_enabled', 'highlight_element', 'dim_background', 'auto_scroll']);
                if ($drop) $table->dropColumn($drop);
            });
        }

        if (Schema::hasTable('user_onboarding_progress')) {
            Schema::table('user_onboarding_progress', function (Blueprint $table) {
                $cols = Schema::getColumnListing('user_onboarding_progress');
                $drop = array_intersect($cols, ['started_at', 'last_viewed_at']);
                if ($drop) $table->dropColumn($drop);
            });
        }
    }
};
