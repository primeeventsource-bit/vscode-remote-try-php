<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', \App\Livewire\Auth\Login::class)->name('login');
});

// Auth routes
Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect('/dashboard'));
    Route::get('/dashboard', \App\Livewire\Dashboard::class)->name('dashboard');
    Route::get('/chargebacks', \App\Livewire\Chargebacks::class)->name('chargebacks');
    Route::get('/leads', \App\Livewire\Leads::class)->name('leads');
    Route::get('/pipeline', \App\Livewire\Pipeline::class)->name('pipeline');
    Route::get('/deals', \App\Livewire\Deals::class)->name('deals');
    Route::get('/verification', \App\Livewire\Verification::class)->name('verification');
    Route::get('/clients', \App\Livewire\Clients::class)->name('clients');
    Route::get('/tasks', \App\Livewire\Tasks::class)->name('tasks');
    Route::get('/tracker', \App\Livewire\Tracker::class)->name('tracker');
    Route::get('/transfers', \App\Livewire\Transfers::class)->name('transfers');
    Route::get('/payroll', \App\Livewire\Payroll::class)->name('payroll');
    Route::get('/stats', \App\Livewire\Statistics::class)->name('stats');
    Route::get('/users', \App\Livewire\Users::class)->name('users');
    Route::get('/settings', \App\Livewire\Settings::class)->name('settings');
    Route::get('/chat', \App\Livewire\ChatPage::class)->name('chat');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/login');
    })->name('logout');
});
