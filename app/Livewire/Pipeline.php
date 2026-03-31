<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Pipeline')]
class Pipeline extends Component
{
    public function render()
    {
        return view('livewire.pipeline');
    }
}
