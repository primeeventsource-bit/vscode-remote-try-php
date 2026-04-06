<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ChargebackController;
use App\Http\Controllers\GifController;

// ─── Public routes ───────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');   // 5 attempts per minute

Route::get('/health', function () {
    $results = \App\Services\Monitor\HealthCheckRunner::runAll();
    $overall = collect($results)->contains('status', 'critical') ? 'critical' : 'healthy';
    return response()->json([
        'status'     => $overall,
        'time'       => now()->toIso8601String(),
        'components' => collect($results)->map(fn ($r) => $r['status']),
    ], $overall === 'critical' ? 503 : 200);
});

// ─── Authenticated routes ────────────────────────────────────
Route::middleware(['auth.token'])->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Users — admin only
    Route::middleware(['role:master_admin,admin'])->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
    });

    // Leads
    Route::get('/leads', [LeadController::class, 'index']);
    Route::post('/leads', [LeadController::class, 'store']);
    Route::post('/leads/import', [LeadController::class, 'import'])
        ->middleware(['role:master_admin,admin', 'throttle:10,1']);
    Route::put('/leads/{id}', [LeadController::class, 'update']);
    Route::put('/leads/{id}/disposition', [LeadController::class, 'disposition']);
    Route::put('/leads/{id}/assign', [LeadController::class, 'assign'])
        ->middleware('role:master_admin,admin,closer');
    Route::delete('/leads/{id}', [LeadController::class, 'destroy'])
        ->middleware('role:master_admin,admin');

    // Deals
    Route::get('/deals', [DealController::class, 'index']);
    Route::post('/deals', [DealController::class, 'store']);
    Route::put('/deals/{id}', [DealController::class, 'update']);
    Route::put('/deals/{id}/charge', [DealController::class, 'toggleCharged'])
        ->middleware('role:master_admin,admin');
    Route::put('/deals/{id}/chargeback', [DealController::class, 'toggleChargeback'])
        ->middleware('role:master_admin,admin');
    Route::delete('/deals/{id}', [DealController::class, 'destroy'])
        ->middleware('role:master_admin,admin');

    // Chat
    Route::get('/chats', [ChatController::class, 'index']);
    Route::post('/chats', [ChatController::class, 'store']);
    Route::get('/chats/{id}/messages', [ChatController::class, 'messages']);
    Route::post('/chats/{id}/messages', [ChatController::class, 'sendMessage']);
    Route::post('/chats/{id}/messages/{msgId}/react', [ChatController::class, 'react']);
    Route::put('/chats/{id}/pin', [ChatController::class, 'togglePin']);
    Route::delete('/chats/{id}', [ChatController::class, 'destroy'])
        ->middleware('role:master_admin,admin');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Chargeback analytics
    Route::get('/dashboard/chargebacks/summary', [ChargebackController::class, 'summary']);
    Route::get('/dashboard/chargebacks/trends', [ChargebackController::class, 'trends']);
    Route::get('/dashboard/chargebacks/breakdowns', [ChargebackController::class, 'breakdowns']);

    // Chargeback management
    Route::get('/chargebacks/filter-options', [ChargebackController::class, 'filterOptions']);
    Route::get('/chargebacks', [ChargebackController::class, 'index']);
    Route::get('/chargebacks/{id}', [ChargebackController::class, 'show']);
    Route::post('/chargebacks', [ChargebackController::class, 'store'])
        ->middleware('role:master_admin,admin');
    Route::patch('/chargebacks/{id}', [ChargebackController::class, 'update'])
        ->middleware('role:master_admin,admin');
    Route::post('/chargebacks/{id}/events', [ChargebackController::class, 'storeEvent'])
        ->middleware('role:master_admin,admin');

    // Payroll — admin only
    Route::match(['get', 'post'], '/payroll', [PayrollController::class, 'handle'])
        ->middleware('role:master_admin,admin');

    // GIFs
    Route::get('/gifs/trending', [GifController::class, 'trending']);
    Route::get('/gifs/search', [GifController::class, 'search']);
    Route::get('/gifs/movies', [GifController::class, 'movies']);
    Route::get('/gifs/recent', [GifController::class, 'recent']);
    Route::get('/gifs/favorites', [GifController::class, 'favorites']);
    Route::post('/gifs/favorites', [GifController::class, 'storeFavorite']);
    Route::delete('/gifs/favorites/{id}', [GifController::class, 'destroyFavorite']);

    // Dialer
    Route::post('/dialer/prepare', [\App\Http\Controllers\DialerController::class, 'prepare']);
    Route::post('/dialer/outcome', [\App\Http\Controllers\DialerController::class, 'saveOutcome']);

});
