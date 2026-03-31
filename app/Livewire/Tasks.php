<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Tasks')]
class Tasks extends Component
{
    public function render()
    {
        return view('livewire.tasks');
    }
}
