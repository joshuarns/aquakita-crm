<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Gestión de leads (§3.3 captura, §4.1 bandeja, §4.2 panel del vendedor).
Route::middleware(['auth'])->group(function () {
    Volt::route('panel', 'panel')->name('panel');

    Volt::route('leads', 'leads.index')->name('leads.index');

    Volt::route('leads/create', 'leads.create')
        ->middleware('permission:leads.capture')
        ->name('leads.create');

    Volt::route('leads/export', 'leads.export')
        ->middleware('permission:leads.export')
        ->name('leads.export');

    Volt::route('leads/{lead}', 'leads.show')->name('leads.show');
});

require __DIR__.'/auth.php';
