<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\DashboardController;

// ─── Public routes ───────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);
Route::get('/health', fn () => response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]));

// ─── Payroll (matches existing /api/payroll?action=XXX format) ──
Route::match(['get', 'post', 'options'], '/payroll', [PayrollController::class, 'handle']);

// ─── Auth-optional routes (CRM works with session/token) ─────
// Using a simple token middleware — or no auth for initial deployment
Route::middleware([])->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Users
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // Leads
    Route::get('/leads', [LeadController::class, 'index']);
    Route::post('/leads', [LeadController::class, 'store']);
    Route::post('/leads/import', [LeadController::class, 'import']);
    Route::put('/leads/{id}', [LeadController::class, 'update']);
    Route::put('/leads/{id}/disposition', [LeadController::class, 'disposition']);
    Route::put('/leads/{id}/assign', [LeadController::class, 'assign']);
    Route::delete('/leads/{id}', [LeadController::class, 'destroy']);

    // Deals
    Route::get('/deals', [DealController::class, 'index']);
    Route::post('/deals', [DealController::class, 'store']);
    Route::put('/deals/{id}', [DealController::class, 'update']);
    Route::put('/deals/{id}/charge', [DealController::class, 'toggleCharged']);
    Route::put('/deals/{id}/chargeback', [DealController::class, 'toggleChargeback']);
    Route::delete('/deals/{id}', [DealController::class, 'destroy']);

    // Chat
    Route::get('/chats', [ChatController::class, 'index']);
    Route::post('/chats', [ChatController::class, 'store']);
    Route::get('/chats/{id}/messages', [ChatController::class, 'messages']);
    Route::post('/chats/{id}/messages', [ChatController::class, 'sendMessage']);
    Route::post('/chats/{id}/messages/{msgId}/react', [ChatController::class, 'react']);
    Route::put('/chats/{id}/pin', [ChatController::class, 'togglePin']);
    Route::delete('/chats/{id}', [ChatController::class, 'destroy']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
