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
    Route::get('/documents', \App\Livewire\Documents::class)->name('documents');
    Route::get('/spreadsheets', \App\Livewire\Spreadsheets::class)->name('spreadsheets');
    Route::get('/video-call/{room?}', \App\Livewire\VideoCall::class)->name('video-call');
    Route::get('/training', \App\Livewire\Onboarding::class)->name('training');
    Route::get('/sales-training', \App\Livewire\SalesTraining::class)->name('sales-training');
    Route::get('/daily-sales', \App\Livewire\DailySalesSystem::class)->name('daily-sales');

    // Presence heartbeat
    Route::post('/presence/heartbeat', function () {
        $user = auth()->user();
        if (!$user) return response()->json(['ok' => false], 401);
        $isActive = (bool) request()->input('active', true);
        \App\Services\Presence\UserPresenceService::markHeartbeat($user, $isActive);
        return response()->json(['ok' => true]);
    })->name('presence.heartbeat');

    Route::post('/logout', function () {
        $user = auth()->user();
        if ($user) {
            \App\Services\Presence\UserPresenceService::markOffline($user);
        }
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/login');
    })->name('logout');
});
