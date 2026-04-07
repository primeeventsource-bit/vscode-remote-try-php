<?php

namespace App\Livewire;

use App\Services\OnboardingService;
use Livewire\Component;

class TrainingOverlay extends Component
{
    public function completeStep(int $stepId): void
    {
        OnboardingService::completeStep(auth()->user(), $stepId);
    }

    public function skipStep(int $stepId): void
    {
        OnboardingService::skipStep(auth()->user(), $stepId);
    }

    public function dismissTraining(): void
    {
        // Mark in session so overlay doesn't auto-show again this session
        session()->put('training_dismissed', true);
    }

    public function render()
    {
        $walkthroughData = null;

        try {
            $user = auth()->user();
            $dismissed = session()->get('training_dismissed', false);

            // Training overlay disabled — auto-start blocks page interaction.
            // Users can access training from the Training & Help page instead.
        } catch (\Throwable $e) {
            // Never crash other components — training overlay is non-critical
        }

        return view('livewire.training-overlay', compact('walkthroughData'));
    }
}
