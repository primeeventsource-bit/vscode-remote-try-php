<?php

namespace App\Livewire;

use App\Services\AI\AiTrainerService;
use App\Services\AI\NoteQualityService;
use Livewire\Component;

/**
 * AI Trainer side panel — embeds into leads, deals, and other pages.
 * Shows coaching, mistakes, next actions, lead/deal scores, and tips.
 */
class AiTrainerPanel extends Component
{
    public string $module = 'leads';    // leads, deals, clients
    public ?int $entityId = null;       // lead ID or deal ID
    public string $activeTab = 'coach'; // coach, mistakes, tips, ask
    public string $askInput = '';
    public ?string $askResponse = null;
    public string $noteInput = '';      // for note quality scoring
    public ?array $noteScore = null;

    public function mount(string $module = 'leads', ?int $entityId = null): void
    {
        $this->module = $module;
        $this->entityId = $entityId;
    }

    // Listen for entity selection changes from parent
    #[\Livewire\Attributes\On('ai-trainer-entity')]
    public function setEntity(string $module, ?int $entityId): void
    {
        $this->module = $module;
        $this->entityId = $entityId;
        $this->askResponse = null;
        $this->noteScore = null;
    }

    public function scoreNote(): void
    {
        if (strlen(trim($this->noteInput)) === 0) return;
        $this->noteScore = NoteQualityService::score($this->noteInput);
    }

    public function dismissRecommendation(int $id): void
    {
        AiTrainerService::dismissRecommendation($id, auth()->id());
    }

    public function completeRecommendation(int $id): void
    {
        AiTrainerService::completeRecommendation($id, auth()->id());
    }

    public function resolveMistake(int $id): void
    {
        AiTrainerService::resolveMistake($id, auth()->id());
    }

    public function render()
    {
        $user = auth()->user();
        $coaching = null;

        if ($user && $this->entityId && AiTrainerService::isEnabled()) {
            try {
                $coaching = AiTrainerService::getFullCoaching($user, $this->module, $this->entityId);
            } catch (\Throwable $e) {
                $coaching = null;
            }
        }

        return view('livewire.ai-trainer-panel', [
            'coaching' => $coaching,
            'trainerEnabled' => AiTrainerService::isEnabled(),
        ]);
    }
}
