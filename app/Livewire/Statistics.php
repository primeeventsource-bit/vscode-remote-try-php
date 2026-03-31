<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Statistics')]
class Statistics extends Component
{
    public function render()
    {
        return view('livewire.statistics');
    }
}
