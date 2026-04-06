<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Deploy helper — runs migrations + clears cache
// Auth: master admin session OR APP_KEY as ?key= query param for CI/CD
Route::get('/deploy-now', function (\Illuminate\Http\Request $request) {
    $authorized = (auth()->check() && auth()->user()->role === 'master_admin')
        || ($request->query('key') === config('app.key'));
    if (!$authorized) return response('Unauthorized', 403);

    $output = [];
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $output[] = 'Migrations: ' . trim(\Illuminate\Support\Facades\Artisan::output());
    } catch (\Throwable $e) { $output[] = 'Migration error: ' . $e->getMessage(); }
    try {
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        \Illuminate\Support\Facades\Artisan::call('route:clear');
        \Illuminate\Support\Facades\Artisan::call('view:clear');
        \Illuminate\Support\Facades\Artisan::call('view:cache');
        \Illuminate\Support\Facades\Artisan::call('storage:link');
        $output[] = 'Caches cleared + storage linked';
    } catch (\Throwable $e) { $output[] = 'Cache error: ' . $e->getMessage(); }
    return response()->json(['status' => 'done', 'output' => $output]);
});

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

    // ─── Meetings API (session-authenticated) ───────────────
    Route::post('/meetings/start-direct', function () {
        $user = auth()->user();
        $otherUserId = (int) request()->input('other_user_id');
        $chatId = request()->input('chat_id') ? (int) request()->input('chat_id') : null;
        if (! $otherUserId) return response()->json(['error' => 'Missing other_user_id'], 400);

        $meeting = \App\Services\Meetings\MeetingService::startDirectCall($user, $otherUserId, $chatId);
        if (! $meeting) return response()->json(['error' => 'Failed to create meeting'], 500);

        return response()->json(['meeting_uuid' => $meeting->uuid, 'room_name' => $meeting->provider_room_name]);
    });

    Route::post('/meetings/start-group', function () {
        $user = auth()->user();
        $participantIds = request()->input('participant_ids', []);
        $title = request()->input('title');
        $chatId = request()->input('chat_id') ? (int) request()->input('chat_id') : null;

        $meeting = \App\Services\Meetings\MeetingService::startGroupMeeting($user, $participantIds, $title, $chatId);
        if (! $meeting) return response()->json(['error' => 'Failed to create meeting'], 500);

        return response()->json(['meeting_uuid' => $meeting->uuid, 'room_name' => $meeting->provider_room_name]);
    });

    Route::post('/meetings/{uuid}/token', function (string $uuid) {
        $user = auth()->user();
        $meeting = \App\Models\Meeting::where('uuid', $uuid)->first();
        if (! $meeting) return response()->json(['error' => 'Meeting not found'], 404);
        if ($meeting->isEnded()) return response()->json(['error' => 'Meeting has ended'], 410);

        $participant = $meeting->participants()->where('user_id', $user->id)->first();
        if (! $participant) return response()->json(['error' => 'Not a participant'], 403);

        $identity = 'user-' . $user->id;

        // Read Twilio credentials directly from env — config() is unreliable on Azure
        $sid = env('TWILIO_ACCOUNT_SID') ?: config('twilio.account_sid') ?: config('services.twilio.account_sid');
        $key = env('TWILIO_API_KEY_SID') ?: config('twilio.api_key_sid') ?: config('services.twilio.api_key_sid');
        $sec = env('TWILIO_API_KEY_SECRET') ?: config('twilio.api_key_secret') ?: config('services.twilio.api_key_secret');

        \Illuminate\Support\Facades\Log::info('Twilio token request', [
            'meeting' => $uuid, 'user' => $user->id,
            'sid_set' => !empty($sid), 'key_set' => !empty($key), 'sec_set' => !empty($sec),
        ]);

        if (!$sid || !$key || !$sec) {
            return response()->json([
                'error' => 'Twilio credentials not configured. Set TWILIO_ACCOUNT_SID, TWILIO_API_KEY_SID, TWILIO_API_KEY_SECRET in Azure App Settings.',
                'missing' => array_filter([
                    !$sid ? 'TWILIO_ACCOUNT_SID' : null,
                    !$key ? 'TWILIO_API_KEY_SID' : null,
                    !$sec ? 'TWILIO_API_KEY_SECRET' : null,
                ]),
            ], 503);
        }

        $token = \App\Services\Twilio\TwilioVideoTokenService::generateToken($identity, $meeting->provider_room_name);
        if (!$token) return response()->json(['error' => 'Token generation failed — check server logs'], 500);

        // Mark as joined
        try { \App\Services\Meetings\MeetingService::joinRoom($meeting, $user); } catch (\Throwable $e) {}

        return response()->json([
            'token'    => $token,
            'identity' => $identity,
            'room'     => $meeting->provider_room_name,
            'meeting'  => ['uuid' => $meeting->uuid, 'title' => $meeting->title, 'type' => $meeting->type, 'status' => $meeting->status],
        ]);
    });

    Route::post('/meetings/{uuid}/accept', function (string $uuid) {
        $meeting = \App\Models\Meeting::where('uuid', $uuid)->first();
        if ($meeting) \App\Services\Meetings\MeetingService::accept($meeting, auth()->user());
        return response()->json(['ok' => true]);
    });

    Route::post('/meetings/{uuid}/decline', function (string $uuid) {
        $meeting = \App\Models\Meeting::where('uuid', $uuid)->first();
        if ($meeting) \App\Services\Meetings\MeetingService::decline($meeting, auth()->user());
        return response()->json(['ok' => true]);
    });

    Route::post('/meetings/{uuid}/leave', function (string $uuid) {
        $meeting = \App\Models\Meeting::where('uuid', $uuid)->first();
        if ($meeting) \App\Services\Meetings\MeetingService::leave($meeting, auth()->user());
        return response()->json(['ok' => true]);
    });

    Route::post('/meetings/{uuid}/end', function (string $uuid) {
        $meeting = \App\Models\Meeting::where('uuid', $uuid)->first();
        if ($meeting) \App\Services\Meetings\MeetingService::endMeeting($meeting, auth()->user());
        return response()->json(['ok' => true]);
    });

    // Meeting room page
    Route::get('/meeting/{uuid}', \App\Livewire\MeetingRoom::class)->name('meeting-room');

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
