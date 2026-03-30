<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

Route::get('/contacts', function () {
    return view('contacts');
})->name('contacts');

Route::get('/deals', function () {
    return view('deals');
})->name('deals');

Route::get('/settings', function () {
    return view('settings');
})->name('settings');
