<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_flows', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30)->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        Schema::create('onboarding_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('onboarding_flows')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('target_route', 100)->nullable();
            $table->string('target_selector', 200)->nullable();
            $table->string('icon', 10)->nullable();
            $table->string('help_link')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['flow_id', 'key']);
            $table->index('sort_order');
        });

        Schema::create('user_onboarding_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('flow_id')->constrained('onboarding_flows')->cascadeOnDelete();
            $table->foreignId('step_id')->constrained('onboarding_steps')->cascadeOnDelete();
            $table->string('status', 20)->default('not_started'); // not_started, completed, skipped
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'step_id']);
            $table->index(['user_id', 'flow_id', 'status']);
        });

        // Seed default flows and steps
        self::seedFlows();
    }

    private static function seedFlows(): void
    {
        $flows = [
            ['role' => 'fronter', 'name' => 'Fronter Onboarding', 'steps' => [
                ['key' => 'welcome', 'title' => 'Welcome to Prime CRM', 'description' => 'Welcome! This guide will walk you through everything you need to know as a Fronter.', 'icon' => '👋', 'target_route' => '/dashboard'],
                ['key' => 'dashboard', 'title' => 'Your Dashboard', 'description' => 'This is your home base. You\'ll see your personal stats, transfers sent, and close rate here.', 'icon' => '📊', 'target_route' => '/dashboard'],
                ['key' => 'leads', 'title' => 'Working Leads', 'description' => 'The Leads tab shows all leads assigned to you. Click any lead to view details, add notes, and update status.', 'icon' => '📋', 'target_route' => '/leads'],
                ['key' => 'edit_lead', 'title' => 'Editing Lead Info', 'description' => 'Click a lead to open it. You can edit contact info, resort details, and add callback dates.', 'icon' => '✏️', 'target_route' => '/leads'],
                ['key' => 'transfer', 'title' => 'Transferring Leads', 'description' => 'When a lead is qualified, transfer it to a Closer. Select the closer from the dropdown and the lead moves to their queue.', 'icon' => '↗️', 'target_route' => '/leads'],
                ['key' => 'chat', 'title' => 'Using Chat', 'description' => 'Use the chat bubble at the bottom-right to message team members. Direct messages and group chats are available.', 'icon' => '💬', 'target_route' => '/chat'],
                ['key' => 'profile', 'title' => 'Your Profile', 'description' => 'Go to Settings → User Profile to upload your avatar, change your name, and set your color.', 'icon' => '👤', 'target_route' => '/settings'],
                ['key' => 'stats', 'title' => 'Your Performance', 'description' => 'Check Statistics to see your transfers sent, deals closed from your leads, and close percentage.', 'icon' => '📈', 'target_route' => '/stats'],
                ['key' => 'complete', 'title' => 'You\'re Ready!', 'description' => 'You\'ve completed Fronter training. Start working your leads and transferring qualified prospects!', 'icon' => '🎉', 'target_route' => '/dashboard'],
            ]],
            ['role' => 'closer', 'name' => 'Closer Onboarding', 'steps' => [
                ['key' => 'welcome', 'title' => 'Welcome to Prime CRM', 'description' => 'Welcome! This guide covers everything you need as a Closer — from receiving transfers to closing deals.', 'icon' => '👋', 'target_route' => '/dashboard'],
                ['key' => 'dashboard', 'title' => 'Your Dashboard', 'description' => 'Your dashboard shows personal pipeline stats: transfers received, deals closed, and revenue.', 'icon' => '📊', 'target_route' => '/dashboard'],
                ['key' => 'deals', 'title' => 'Managing Deals', 'description' => 'The Deals tab shows all your active deals. Click any deal to view full details, edit info, and track status.', 'icon' => '📋', 'target_route' => '/deals'],
                ['key' => 'create_deal', 'title' => 'Converting Leads to Deals', 'description' => 'When a transferred lead is ready, convert it to a deal. Fill in the deal form with all required info.', 'icon' => '✅', 'target_route' => '/leads'],
                ['key' => 'verification', 'title' => 'Sending to Verification', 'description' => 'After closing a deal, send it to an Admin for verification. Select the admin from the dropdown.', 'icon' => '🔍', 'target_route' => '/verification'],
                ['key' => 'payroll', 'title' => 'Understanding Payroll', 'description' => 'Your commission = Deal Fee minus SNR% minus VD% (if applicable). Check Payroll to see your earnings.', 'icon' => '💰', 'target_route' => '/payroll'],
                ['key' => 'chat', 'title' => 'Team Chat', 'description' => 'Use chat for real-time communication. You can also start video/audio calls from DM threads.', 'icon' => '💬', 'target_route' => '/chat'],
                ['key' => 'transfer_closer', 'title' => 'Closer-to-Closer Transfers', 'description' => 'You can transfer deals to another closer. A note is required explaining why.', 'icon' => '🔄', 'target_route' => '/deals'],
                ['key' => 'complete', 'title' => 'You\'re Ready!', 'description' => 'You\'ve completed Closer training. Start converting leads and closing deals!', 'icon' => '🎉', 'target_route' => '/dashboard'],
            ]],
            ['role' => 'admin', 'name' => 'Admin Onboarding', 'steps' => [
                ['key' => 'welcome', 'title' => 'Welcome, Admin', 'description' => 'This guide covers your admin responsibilities: verification, user management, and operations oversight.', 'icon' => '👋', 'target_route' => '/dashboard'],
                ['key' => 'dashboard', 'title' => 'Admin Dashboard', 'description' => 'Your dashboard shows company-wide stats, task list, and pipeline performance.', 'icon' => '📊', 'target_route' => '/dashboard'],
                ['key' => 'verification', 'title' => 'Deal Verification', 'description' => 'The Verification tab shows deals sent to you. Review and either Charge (green) or Decline.', 'icon' => '✓', 'target_route' => '/verification'],
                ['key' => 'clients', 'title' => 'Client Management', 'description' => 'Clients are charged deals. You can edit client info, view deal sheets, banking, and payment data.', 'icon' => '💰', 'target_route' => '/clients'],
                ['key' => 'chargebacks', 'title' => 'Chargeback System', 'description' => 'Manage chargeback cases, upload required evidence, and track case readiness for submission.', 'icon' => '⚠️', 'target_route' => '/clients'],
                ['key' => 'users', 'title' => 'User Management', 'description' => 'Create and manage user accounts. Assign roles and control who accesses what.', 'icon' => '👥', 'target_route' => '/users'],
                ['key' => 'documents', 'title' => 'Documents & Sheets', 'description' => 'Create, edit, and share documents and spreadsheets with your team.', 'icon' => '📄', 'target_route' => '/documents'],
                ['key' => 'settings', 'title' => 'CRM Settings', 'description' => 'Configure leads, deals, chat, notifications, and other operational settings.', 'icon' => '⚙️', 'target_route' => '/settings'],
                ['key' => 'complete', 'title' => 'Admin Training Complete', 'description' => 'You\'re ready to manage operations. Check the task list daily for action items.', 'icon' => '🎉', 'target_route' => '/dashboard'],
            ]],
            ['role' => 'master_admin', 'name' => 'Master Admin Onboarding', 'steps' => [
                ['key' => 'welcome', 'title' => 'Welcome, Master Admin', 'description' => 'You have full control over the CRM. This guide covers system-wide management.', 'icon' => '👋', 'target_route' => '/dashboard'],
                ['key' => 'dashboard', 'title' => 'Company Dashboard', 'description' => 'See company-wide pipeline, revenue, task list, and all-team performance at a glance.', 'icon' => '📊', 'target_route' => '/dashboard'],
                ['key' => 'users', 'title' => 'User & Role Control', 'description' => 'Create users, assign roles, manage permissions. You control who can do what.', 'icon' => '👥', 'target_route' => '/users'],
                ['key' => 'settings', 'title' => 'Full Settings Control', 'description' => 'You control all CRM settings: payroll rates, chat, chargebacks, presence, video calls, and more.', 'icon' => '⚙️', 'target_route' => '/settings'],
                ['key' => 'payroll', 'title' => 'Payroll & Commissions', 'description' => 'Set commission rates (SNR%, VD%, closer%, fronter%). Changes affect all future deal calculations.', 'icon' => '💵', 'target_route' => '/payroll'],
                ['key' => 'chargebacks', 'title' => 'Chargeback Management', 'description' => 'Full access to chargeback cases, evidence uploads, and case submission workflows.', 'icon' => '⚠️', 'target_route' => '/clients'],
                ['key' => 'statistics', 'title' => 'Pipeline Statistics', 'description' => 'View fronter, closer, and admin performance with filters by agent and time range.', 'icon' => '📈', 'target_route' => '/stats'],
                ['key' => 'notes', 'title' => 'Notes System', 'description' => 'Add notes to clients and deals for fulfillment proof and chargeback defense.', 'icon' => '📝', 'target_route' => '/clients'],
                ['key' => 'security', 'title' => 'Security & Audit', 'description' => 'Monitor user presence, audit client edits, and control who accesses sensitive data.', 'icon' => '🔒', 'target_route' => '/settings'],
                ['key' => 'complete', 'title' => 'Full Access Activated', 'description' => 'You have complete CRM control. Use Settings to customize everything.', 'icon' => '🎉', 'target_route' => '/dashboard'],
            ]],
        ];

        foreach ($flows as $flowData) {
            $steps = $flowData['steps'];
            unset($flowData['steps']);
            $flowData['created_at'] = now();
            $flowData['updated_at'] = now();

            $flowId = DB::table('onboarding_flows')->insertGetId($flowData);

            foreach ($steps as $i => $step) {
                $step['flow_id'] = $flowId;
                $step['sort_order'] = $i;
                $step['is_required'] = ($step['key'] !== 'complete');
                $step['created_at'] = now();
                $step['updated_at'] = now();
                DB::table('onboarding_steps')->insert($step);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_onboarding_progress');
        Schema::dropIfExists('onboarding_steps');
        Schema::dropIfExists('onboarding_flows');
    }
};
