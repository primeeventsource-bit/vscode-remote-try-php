<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'app' => 'Prime CRM',
        'timestamp' => now()->toIso8601String(),
    ]);
});
