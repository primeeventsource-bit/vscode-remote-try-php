<?php

namespace App\Services;

use App\Models\OnboardingFlow;
use App\Models\OnboardingStep;
use App\Models\OnboardingStepImage;
use App\Models\TrainingCompletionSummary;
use App\Models\User;
use App\Models\UserOnboardingProgress;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class OnboardingService
{
    private static function ready(): bool
    {
        return Schema::hasTable('onboarding_flows');
    }

    // ── Role mapping ───────────────────────────────────────
    public static function normalizeRole(string $role): string
    {
        if (in_array($role, ['fronter', 'fronter_panama'])) return 'fronter';
        if (in_array($role, ['closer', 'closer_panama'])) return 'closer';
        if ($role === 'admin_limited') return 'admin';
        return $role;
    }

    public static function getFlowForUser(User $user): ?OnboardingFlow
    {
        if (!self::ready()) return null;
        return OnboardingFlow::forRole(self::normalizeRole($user->role));
    }

    // ── User Progress ──────────────────────────────────────
    public static function getUserProgress(User $user): array
    {
        $empty = ['flow' => null, 'steps' => [], 'completed' => 0, 'total' => 0, 'pct' => 0, 'current_step' => null, 'is_complete' => false];
        if (!self::ready()) return $empty;

        $flow = self::getFlowForUser($user);
        if (!$flow) return $empty;

        $steps = $flow->enabledSteps;
        $progress = UserOnboardingProgress::where('user_id', $user->id)
            ->where('flow_id', $flow->id)
            ->pluck('status', 'step_id')
            ->toArray();

        $completed = 0;
        $currentStep = null;
        $stepData = [];

        foreach ($steps as $step) {
            $status = $progress[$step->id] ?? 'not_started';
            if (in_array($status, ['completed', 'skipped'])) $completed++;
            if ($status === 'not_started' && !$currentStep) $currentStep = $step;

            $stepData[] = [
                'id' => $step->id,
                'key' => $step->key,
                'title' => $step->title,
                'description' => $step->description,
                'step_type' => $step->step_type ?? 'tooltip',
                'target_route' => $step->target_route,
                'target_selector' => $step->target_selector,
                'action_event' => $step->action_event,
                'action_value' => $step->action_value,
                'tooltip_position' => $step->tooltip_position ?? 'bottom',
                'icon' => $step->icon ?? '📌',
                'image_path' => $step->image_path,
                'image_caption' => $step->image_caption,
                'tip_text' => $step->tip_text,
                'highlight_element' => $step->highlight_element ?? true,
                'dim_background' => $step->dim_background ?? true,
                'auto_scroll' => $step->auto_scroll ?? true,
                'status' => $status,
                'is_required' => $step->is_required,
                'sort_order' => $step->sort_order,
            ];
        }

        $total = $steps->count();
        $pct = $total > 0 ? round($completed / $total * 100) : 0;

        // Update completion summary
        self::updateCompletionSummary($user, $flow, $currentStep, $pct, $completed >= $total);

        return [
            'flow' => $flow,
            'steps' => $stepData,
            'completed' => $completed,
            'total' => $total,
            'pct' => $pct,
            'current_step' => $currentStep,
            'is_complete' => $completed >= $total,
        ];
    }

    private static function updateCompletionSummary(User $user, OnboardingFlow $flow, ?OnboardingStep $currentStep, int $pct, bool $isComplete): void
    {
        if (!Schema::hasTable('training_completion_summary')) return;

        $data = [
            'current_step_id' => $currentStep?->id,
            'progress_percent' => $pct,
            'last_viewed_at' => now(),
        ];

        if ($isComplete) {
            $data['completed_at'] = now();
        }

        $summary = TrainingCompletionSummary::where('user_id', $user->id)->where('flow_id', $flow->id)->first();
        if (!$summary) {
            $data['started_at'] = now();
        }

        TrainingCompletionSummary::updateOrCreate(
            ['user_id' => $user->id, 'flow_id' => $flow->id],
            $data
        );
    }

    // ── Step actions ───────────────────────────────────────
    public static function completeStep(User $user, int $stepId): void
    {
        if (!self::ready()) return;
        $step = OnboardingStep::find($stepId);
        if (!$step) return;

        UserOnboardingProgress::updateOrCreate(
            ['user_id' => $user->id, 'step_id' => $stepId],
            ['flow_id' => $step->flow_id, 'status' => 'completed', 'completed_at' => now(), 'skipped_at' => null, 'last_viewed_at' => now()]
        );
    }

    public static function skipStep(User $user, int $stepId): void
    {
        if (!self::ready()) return;
        $step = OnboardingStep::find($stepId);
        if (!$step) return;

        UserOnboardingProgress::updateOrCreate(
            ['user_id' => $user->id, 'step_id' => $stepId],
            ['flow_id' => $step->flow_id, 'status' => 'skipped', 'skipped_at' => now(), 'last_viewed_at' => now()]
        );
    }

    public static function viewStep(User $user, int $stepId): void
    {
        if (!self::ready()) return;
        $step = OnboardingStep::find($stepId);
        if (!$step) return;

        $progress = UserOnboardingProgress::where('user_id', $user->id)->where('step_id', $stepId)->first();
        if (!$progress) {
            UserOnboardingProgress::create([
                'user_id' => $user->id,
                'step_id' => $stepId,
                'flow_id' => $step->flow_id,
                'status' => 'not_started',
                'started_at' => now(),
                'last_viewed_at' => now(),
            ]);
        } else {
            $progress->update(['last_viewed_at' => now()]);
        }
    }

    public static function resetForUser(User $user): void
    {
        if (!self::ready()) return;
        UserOnboardingProgress::where('user_id', $user->id)->delete();
        if (Schema::hasTable('training_completion_summary')) {
            TrainingCompletionSummary::where('user_id', $user->id)->delete();
        }
    }

    public static function needsOnboarding(User $user): bool
    {
        if (!self::ready()) return false;
        $progress = self::getUserProgress($user);
        return !$progress['is_complete'] && $progress['total'] > 0;
    }

    // ── Admin: get all users progress ──────────────────────
    public static function getAllUsersProgress(): array
    {
        if (!self::ready()) return [];

        $users = User::orderBy('name')->get();
        $result = [];

        foreach ($users as $user) {
            $p = self::getUserProgress($user);
            $result[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'avatar' => $user->avatar,
                'color' => $user->color,
                'completed' => $p['completed'],
                'total' => $p['total'],
                'pct' => $p['pct'],
                'is_complete' => $p['is_complete'],
            ];
        }

        return $result;
    }

    // ── Admin: Guide Builder CRUD ──────────────────────────

    public static function getAllFlows(): \Illuminate\Database\Eloquent\Collection
    {
        return OnboardingFlow::with('steps')->orderBy('role')->get();
    }

    public static function createFlow(array $data, int $userId): OnboardingFlow
    {
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;
        return OnboardingFlow::create($data);
    }

    public static function updateFlow(int $flowId, array $data, int $userId): ?OnboardingFlow
    {
        $flow = OnboardingFlow::find($flowId);
        if (!$flow) return null;
        $data['updated_by'] = $userId;
        $flow->update($data);
        return $flow->fresh();
    }

    public static function deleteFlow(int $flowId): bool
    {
        $flow = OnboardingFlow::find($flowId);
        if (!$flow) return false;
        $flow->delete();
        return true;
    }

    // ── Step CRUD ──────────────────────────────────────────

    public static function createStep(int $flowId, array $data): ?OnboardingStep
    {
        $flow = OnboardingFlow::find($flowId);
        if (!$flow) return null;

        $maxOrder = $flow->steps()->max('sort_order') ?? -1;
        $data['flow_id'] = $flowId;
        $data['sort_order'] = $data['sort_order'] ?? $maxOrder + 1;
        $data['key'] = $data['key'] ?? \Illuminate\Support\Str::slug($data['title'] ?? 'step', '_');

        return OnboardingStep::create($data);
    }

    public static function updateStep(int $stepId, array $data): ?OnboardingStep
    {
        $step = OnboardingStep::find($stepId);
        if (!$step) return null;
        $step->update($data);
        return $step->fresh();
    }

    public static function deleteStep(int $stepId): bool
    {
        $step = OnboardingStep::find($stepId);
        if (!$step) return false;
        $step->delete();
        return true;
    }

    public static function reorderSteps(int $flowId, array $orderedIds): void
    {
        foreach ($orderedIds as $i => $stepId) {
            OnboardingStep::where('id', $stepId)->where('flow_id', $flowId)->update(['sort_order' => $i]);
        }
    }

    // ── Step Image Management ──────────────────────────────

    public static function addStepImage(int $stepId, $uploadedFile, ?string $caption = null): ?OnboardingStepImage
    {
        $step = OnboardingStep::find($stepId);
        if (!$step) return null;

        $path = $uploadedFile->store('training/step-images', 'public');
        $maxOrder = OnboardingStepImage::where('step_id', $stepId)->max('sort_order') ?? -1;

        return OnboardingStepImage::create([
            'step_id' => $stepId,
            'image_path' => $path,
            'caption' => $caption,
            'sort_order' => $maxOrder + 1,
        ]);
    }

    public static function deleteStepImage(int $imageId): bool
    {
        $image = OnboardingStepImage::find($imageId);
        if (!$image) return false;

        if ($image->image_path && Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }
        $image->delete();
        return true;
    }

    // ── Interactive Walkthrough Data ───────────────────────
    public static function getWalkthroughData(User $user): ?array
    {
        $flow = self::getFlowForUser($user);
        if (!$flow) return null;

        $progress = self::getUserProgress($user);
        if ($progress['is_complete']) return null;

        $currentStep = $progress['current_step'];
        if (!$currentStep) return null;

        return [
            'flow_id' => $flow->id,
            'flow_name' => $flow->name,
            'allow_skip' => $flow->allow_skip ?? true,
            'lock_ui' => $flow->lock_ui_during_training ?? false,
            'steps' => $progress['steps'],
            'current_step_index' => collect($progress['steps'])->search(fn($s) => $s['id'] === $currentStep->id),
            'pct' => $progress['pct'],
            'total' => $progress['total'],
            'completed' => $progress['completed'],
        ];
    }
}
