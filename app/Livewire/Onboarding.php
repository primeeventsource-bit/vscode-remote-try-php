<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\OnboardingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Training & Help')]
class Onboarding extends Component
{
    public string $section = 'my_training';

    public function completeStep(int $stepId): void
    {
        OnboardingService::completeStep(auth()->user(), $stepId);
    }

    public function skipStep(int $stepId): void
    {
        OnboardingService::skipStep(auth()->user(), $stepId);
    }

    public function resetMyOnboarding(): void
    {
        OnboardingService::resetForUser(auth()->user());
    }

    public function resetUserOnboarding(int $userId): void
    {
        if (!auth()->user()?->hasRole('master_admin')) return;
        $user = User::find($userId);
        if ($user) OnboardingService::resetForUser($user);
    }

    public function render()
    {
        $user = auth()->user();
        $progress = OnboardingService::getUserProgress($user);
        $isMaster = $user->hasRole('master_admin');
        $isAdmin = $user->hasRole('master_admin', 'admin');

        $allUsersProgress = [];
        if ($isAdmin) {
            $allUsersProgress = OnboardingService::getAllUsersProgress();
        }

        return view('livewire.onboarding', compact('progress', 'isMaster', 'isAdmin', 'allUsersProgress'));
    }
}
