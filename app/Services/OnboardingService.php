<?php

namespace App\Services;

use App\Models\OnboardingFlow;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Models\UserOnboardingProgress;
use Illuminate\Support\Facades\Schema;

class OnboardingService
{
    private static function ready(): bool
    {
        return Schema::hasTable('onboarding_flows');
    }

    public static function getFlowForUser(User $user): ?OnboardingFlow
    {
        if (!self::ready()) return null;
        $role = $user->role;
        // Map role variants to flow roles
        if (in_array($role, ['fronter', 'fronter_panama'])) $role = 'fronter';
        if ($role === 'admin_limited') $role = 'admin';
        return OnboardingFlow::forRole($role);
    }

    public static function getUserProgress(User $user): array
    {
        if (!self::ready()) return ['flow' => null, 'steps' => [], 'completed' => 0, 'total' => 0, 'pct' => 0, 'current_step' => null, 'is_complete' => false];

        $flow = self::getFlowForUser($user);
        if (!$flow) return ['flow' => null, 'steps' => [], 'completed' => 0, 'total' => 0, 'pct' => 0, 'current_step' => null, 'is_complete' => false];

        $steps = $flow->steps;
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
                'icon' => $step->icon ?? '📌',
                'target_route' => $step->target_route,
                'status' => $status,
                'is_required' => $step->is_required,
                'sort_order' => $step->sort_order,
            ];
        }

        $total = $steps->count();
        return [
            'flow' => $flow,
            'steps' => $stepData,
            'completed' => $completed,
            'total' => $total,
            'pct' => $total > 0 ? round($completed / $total * 100) : 0,
            'current_step' => $currentStep,
            'is_complete' => $completed >= $total,
        ];
    }

    public static function completeStep(User $user, int $stepId): void
    {
        if (!self::ready()) return;
        $step = OnboardingStep::find($stepId);
        if (!$step) return;

        UserOnboardingProgress::updateOrCreate(
            ['user_id' => $user->id, 'step_id' => $stepId],
            ['flow_id' => $step->flow_id, 'status' => 'completed', 'completed_at' => now(), 'skipped_at' => null]
        );
    }

    public static function skipStep(User $user, int $stepId): void
    {
        if (!self::ready()) return;
        $step = OnboardingStep::find($stepId);
        if (!$step) return;

        UserOnboardingProgress::updateOrCreate(
            ['user_id' => $user->id, 'step_id' => $stepId],
            ['flow_id' => $step->flow_id, 'status' => 'skipped', 'skipped_at' => now()]
        );
    }

    public static function resetForUser(User $user): void
    {
        if (!self::ready()) return;
        UserOnboardingProgress::where('user_id', $user->id)->delete();
    }

    public static function needsOnboarding(User $user): bool
    {
        if (!self::ready()) return false;
        $progress = self::getUserProgress($user);
        return !$progress['is_complete'] && $progress['total'] > 0;
    }

    /**
     * Get all users' onboarding status for admin reporting.
     */
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
}
