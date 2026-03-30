<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    try {
        return view('crm');
    } catch (Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 500);
    }
});

Route::get('/test', function () {
    return response()->json(['status' => 'ok', 'time' => now()]);
});
