<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class Login extends Component
{
    public string $username = '';
    public string $password = '';
    public string $error = '';

    public function authenticate()
    {
        $this->validate([
            'username' => 'required',
            'password' => 'required|min:8',
        ]);

        if (Auth::attempt(['username' => $this->username, 'password' => $this->password])) {
            session()->regenerate();

            $user = Auth::user();
            $redirect = $user->hasPerm('view_dashboard') ? '/dashboard'
                : ($user->hasPerm('view_leads') ? '/leads' : '/chat');

            return redirect()->intended($redirect);
        }

        $this->error = 'Invalid username or password';
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
