<?php

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Documents')]
class Documents extends Component
{
    private function moduleEnabled(string $key, bool $default = true): bool
    {
        $raw = DB::table('crm_settings')->where('key', $key)->value('value');
        if ($raw === null) {
            return $default;
        }

        $decoded = json_decode($raw, true);
        return is_bool($decoded) ? $decoded : $default;
    }

    public function mount(): void
    {
        $enabled = $this->moduleEnabled('documents.module_enabled');

        if (!$enabled || !auth()->user()?->hasPerm('view_documents')) {
            $this->redirectRoute('dashboard');
            session()->flash('error', 'Documents module is disabled or you do not have access.');
        }
    }

    public function render()
    {
        return view('livewire.documents');
    }
}
