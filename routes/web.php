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
    Route::get('/meetings', \App\Livewire\Meetings::class)->name('meetings');
    Route::get('/training', \App\Livewire\Onboarding::class)->name('training');
    Route::get('/sales-training', \App\Livewire\SalesTraining::class)->name('sales-training');
    Route::get('/daily-sales', \App\Livewire\DailySalesSystem::class)->name('daily-sales');
    Route::get('/script-editor/{id?}', \App\Livewire\ScriptEditor::class)->name('script-editor');
    Route::get('/system-monitor', \App\Livewire\SystemMonitor::class)->name('system-monitor');

    // Enterprise Lead Management
    Route::get('/leads/duplicates', \App\Livewire\DuplicateReview::class)->name('duplicate-review');
    Route::get('/leads/imports', \App\Livewire\LeadImports::class)->name('lead-imports');

    // Twilio ICE servers for video calls (web route — session-authenticated)
    Route::get('/ice-servers', function () {
        return response()->json([
            'iceServers' => \App\Services\TwilioIceService::getIceServers(),
        ]);
    })->name('ice-servers');

    // Twilio Video access token for group rooms
    Route::post('/video-token', function () {
        $user = auth()->user();
        if (! $user) return response()->json(['error' => 'Unauthorized'], 401);

        $roomName = request()->input('room_name');
        if (! $roomName) return response()->json(['error' => 'Room name required'], 400);

        $identity = 'user-' . $user->id;
        $token = \App\Services\Twilio\TwilioVideoTokenService::generateToken($identity, $roomName);

        if (! $token) return response()->json(['error' => 'Failed to generate video token'], 500);

        return response()->json([
            'token'    => $token,
            'identity' => $identity,
            'room'     => $roomName,
        ]);
    })->name('video-token');

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

// ─── Twilio Webhooks (public, CSRF-exempt, signature-validated) ──
Route::prefix('webhooks/twilio')->middleware(\App\Http\Middleware\ValidateTwilioWebhook::class)->group(function () {
    Route::post('/messages/inbound', [\App\Http\Controllers\Webhooks\TwilioWebhookController::class, 'inboundMessage']);
    Route::post('/messages/status', [\App\Http\Controllers\Webhooks\TwilioWebhookController::class, 'messageStatus']);
});
