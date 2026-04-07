<?php

namespace App\Livewire;

use App\Models\OnboardingFlow;
use App\Models\OnboardingStep;
use App\Models\OnboardingStepImage;
use App\Models\User;
use App\Services\OnboardingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Training & Help')]
class Onboarding extends Component
{
    use WithFileUploads;

    public string $section = 'my_training';

    // ── Step-by-step viewer state ──────────────────────────
    public ?int $activeStepIndex = null;

    // ── Admin: Guide Builder state ─────────────────────────
    public bool $showGuideModal = false;
    public bool $editingGuide = false;
    public ?int $editGuideId = null;
    public array $guideForm = [
        'name' => '', 'role' => 'fronter', 'description' => '',
        'is_active' => true, 'is_published' => true,
        'auto_start_on_first_login' => true, 'allow_skip' => true,
        'lock_ui_during_training' => false,
    ];

    // ── Admin: Step editor state ───────────────────────────
    public ?int $editingFlowId = null;
    public bool $showStepModal = false;
    public bool $editingStep = false;
    public ?int $editStepId = null;
    public array $stepForm = [
        'title' => '', 'description' => '', 'step_type' => 'tooltip',
        'target_route' => '', 'target_selector' => '',
        'action_event' => '', 'action_value' => '',
        'tooltip_position' => 'bottom', 'icon' => '📌',
        'tip_text' => '', 'is_required' => true, 'is_enabled' => true,
        'highlight_element' => true, 'dim_background' => true, 'auto_scroll' => true,
    ];
    public $stepImage = null; // file upload
    public $stepImageCaption = '';

    // ── User Training Actions ──────────────────────────────

    public function completeStep(int $stepId): void
    {
        OnboardingService::completeStep(auth()->user(), $stepId);
        $this->advanceToNextStep();
    }

    public function skipStep(int $stepId): void
    {
        OnboardingService::skipStep(auth()->user(), $stepId);
        $this->advanceToNextStep();
    }

    public function goToStep(int $index): void
    {
        $this->activeStepIndex = $index;
    }

    private function advanceToNextStep(): void
    {
        $progress = OnboardingService::getUserProgress(auth()->user());
        foreach ($progress['steps'] as $i => $step) {
            if ($step['status'] === 'not_started') {
                $this->activeStepIndex = $i;
                return;
            }
        }
        $this->activeStepIndex = null; // all done
    }

    public function startTraining(): void
    {
        $this->activeStepIndex = 0;
        $progress = OnboardingService::getUserProgress(auth()->user());
        foreach ($progress['steps'] as $i => $step) {
            if ($step['status'] === 'not_started') {
                $this->activeStepIndex = $i;
                return;
            }
        }
    }

    public function resetMyOnboarding(): void
    {
        OnboardingService::resetForUser(auth()->user());
        $this->activeStepIndex = null;
    }

    public function resetUserOnboarding(int $userId): void
    {
        if (!auth()->user()?->hasRole('master_admin')) return;
        $user = User::find($userId);
        if ($user) OnboardingService::resetForUser($user);
    }

    // ── Admin: Guide CRUD ──────────────────────────────────

    public function openNewGuide(): void
    {
        $this->guideForm = [
            'name' => '', 'role' => 'fronter', 'description' => '',
            'is_active' => true, 'is_published' => true,
            'auto_start_on_first_login' => true, 'allow_skip' => true,
            'lock_ui_during_training' => false,
        ];
        $this->editingGuide = false;
        $this->editGuideId = null;
        $this->showGuideModal = true;
    }

    public function editGuide(int $flowId): void
    {
        $flow = OnboardingFlow::find($flowId);
        if (!$flow) return;

        $this->guideForm = [
            'name' => $flow->name,
            'role' => $flow->role,
            'description' => $flow->description ?? '',
            'is_active' => (bool) $flow->is_active,
            'is_published' => (bool) ($flow->is_published ?? true),
            'auto_start_on_first_login' => (bool) ($flow->auto_start_on_first_login ?? true),
            'allow_skip' => (bool) ($flow->allow_skip ?? true),
            'lock_ui_during_training' => (bool) ($flow->lock_ui_during_training ?? false),
        ];
        $this->editingGuide = true;
        $this->editGuideId = $flowId;
        $this->showGuideModal = true;
    }

    public function saveGuide(): void
    {
        $user = auth()->user();
        if (!$user?->hasRole('master_admin', 'admin')) return;

        $this->validate([
            'guideForm.name' => 'required|string|max:255',
            'guideForm.role' => 'required|string|max:30',
        ]);

        if ($this->editingGuide && $this->editGuideId) {
            OnboardingService::updateFlow($this->editGuideId, $this->guideForm, $user->id);
        } else {
            OnboardingService::createFlow($this->guideForm, $user->id);
        }

        $this->showGuideModal = false;
        $this->editGuideId = null;
    }

    public function deleteGuide(int $flowId): void
    {
        if (!auth()->user()?->hasRole('master_admin')) return;
        OnboardingService::deleteFlow($flowId);
        if ($this->editingFlowId === $flowId) $this->editingFlowId = null;
    }

    public function manageSteps(int $flowId): void
    {
        $this->editingFlowId = $flowId;
    }

    public function closeStepEditor(): void
    {
        $this->editingFlowId = null;
    }

    // ── Admin: Step CRUD ───────────────────────────────────

    public function openNewStep(): void
    {
        $this->stepForm = [
            'title' => '', 'description' => '', 'step_type' => 'tooltip',
            'target_route' => '', 'target_selector' => '',
            'action_event' => '', 'action_value' => '',
            'tooltip_position' => 'bottom', 'icon' => '📌',
            'tip_text' => '', 'is_required' => true, 'is_enabled' => true,
            'highlight_element' => true, 'dim_background' => true, 'auto_scroll' => true,
        ];
        $this->editingStep = false;
        $this->editStepId = null;
        $this->stepImage = null;
        $this->stepImageCaption = '';
        $this->showStepModal = true;
    }

    public function editStep(int $stepId): void
    {
        $step = OnboardingStep::find($stepId);
        if (!$step) return;

        $this->stepForm = [
            'title' => $step->title,
            'description' => $step->description ?? '',
            'step_type' => $step->step_type ?? 'tooltip',
            'target_route' => $step->target_route ?? '',
            'target_selector' => $step->target_selector ?? '',
            'action_event' => $step->action_event ?? '',
            'action_value' => $step->action_value ?? '',
            'tooltip_position' => $step->tooltip_position ?? 'bottom',
            'icon' => $step->icon ?? '📌',
            'tip_text' => $step->tip_text ?? '',
            'is_required' => (bool) $step->is_required,
            'is_enabled' => (bool) ($step->is_enabled ?? true),
            'highlight_element' => (bool) ($step->highlight_element ?? true),
            'dim_background' => (bool) ($step->dim_background ?? true),
            'auto_scroll' => (bool) ($step->auto_scroll ?? true),
        ];
        $this->editingStep = true;
        $this->editStepId = $stepId;
        $this->stepImage = null;
        $this->stepImageCaption = '';
        $this->showStepModal = true;
    }

    public function saveStep(): void
    {
        $user = auth()->user();
        if (!$user?->hasRole('master_admin', 'admin')) return;
        if (!$this->editingFlowId) return;

        $this->validate([
            'stepForm.title' => 'required|string|max:255',
            'stepForm.step_type' => 'required|in:tooltip,action,info,screenshot',
            'stepImage' => 'nullable|image|max:10240',
        ]);

        if ($this->editingStep && $this->editStepId) {
            $step = OnboardingService::updateStep($this->editStepId, $this->stepForm);
        } else {
            $step = OnboardingService::createStep($this->editingFlowId, $this->stepForm);
        }

        // Upload image if provided
        if ($step && $this->stepImage) {
            $path = $this->stepImage->store('training/step-images', 'public');
            $step->update(['image_path' => $path, 'image_caption' => $this->stepImageCaption ?: null]);
        }

        $this->showStepModal = false;
        $this->editStepId = null;
        $this->stepImage = null;
    }

    public function deleteStep(int $stepId): void
    {
        if (!auth()->user()?->hasRole('master_admin', 'admin')) return;
        OnboardingService::deleteStep($stepId);
    }

    public function toggleStep(int $stepId): void
    {
        $step = OnboardingStep::find($stepId);
        if (!$step) return;
        $step->update(['is_enabled' => !$step->is_enabled]);
    }

    public function moveStepUp(int $stepId): void
    {
        if (!$this->editingFlowId) return;
        $steps = OnboardingStep::where('flow_id', $this->editingFlowId)->orderBy('sort_order')->get();
        $index = $steps->search(fn($s) => $s->id === $stepId);
        if ($index === false || $index === 0) return;

        $prev = $steps[$index - 1];
        $curr = $steps[$index];
        $tmpOrder = $prev->sort_order;
        $prev->update(['sort_order' => $curr->sort_order]);
        $curr->update(['sort_order' => $tmpOrder]);
    }

    public function moveStepDown(int $stepId): void
    {
        if (!$this->editingFlowId) return;
        $steps = OnboardingStep::where('flow_id', $this->editingFlowId)->orderBy('sort_order')->get();
        $index = $steps->search(fn($s) => $s->id === $stepId);
        if ($index === false || $index >= $steps->count() - 1) return;

        $next = $steps[$index + 1];
        $curr = $steps[$index];
        $tmpOrder = $next->sort_order;
        $next->update(['sort_order' => $curr->sort_order]);
        $curr->update(['sort_order' => $tmpOrder]);
    }

    // ── Step Image Upload (additional images) ──────────────
    public $additionalImage = null;
    public string $additionalCaption = '';

    public function uploadStepImage(int $stepId): void
    {
        if (!auth()->user()?->hasRole('master_admin', 'admin')) return;
        if (!$this->additionalImage) return;

        $this->validate(['additionalImage' => 'image|max:10240']);
        OnboardingService::addStepImage($stepId, $this->additionalImage, $this->additionalCaption ?: null);
        $this->additionalImage = null;
        $this->additionalCaption = '';
    }

    public function deleteStepImage(int $imageId): void
    {
        if (!auth()->user()?->hasRole('master_admin', 'admin')) return;
        OnboardingService::deleteStepImage($imageId);
    }

    // ── Render ──────────────────────────────────────────────

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

        $allFlows = [];
        $editingFlow = null;
        if ($isAdmin) {
            $allFlows = OnboardingService::getAllFlows();
        }
        if ($this->editingFlowId) {
            $editingFlow = OnboardingFlow::with(['steps' => fn($q) => $q->orderBy('sort_order')])->find($this->editingFlowId);
        }

        // Available roles for guide builder
        $availableRoles = ['master_admin', 'admin', 'fronter', 'closer', 'fronter_panama', 'closer_panama', 'admin_limited', 'agent'];

        return view('livewire.onboarding', compact(
            'progress', 'isMaster', 'isAdmin', 'allUsersProgress',
            'allFlows', 'editingFlow', 'availableRoles'
        ));
    }
}
