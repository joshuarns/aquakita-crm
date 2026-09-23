<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Public\LeadFormController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', 'dashboard');

// Formularios web embebibles — endpoints públicos (sin autenticación).
Route::get('f/{token}.js', [LeadFormController::class, 'script'])->whereAlphaNumeric('token')->name('public.forms.script');
Route::get('f/{token}', [LeadFormController::class, 'show'])->whereAlphaNumeric('token')->name('public.forms.show');
Route::post('f/{token}', [LeadFormController::class, 'submit'])->whereAlphaNumeric('token')->name('public.forms.submit');

Volt::route('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Gestión de leads (§3.3 captura, §4.1 bandeja, §4.2 panel del vendedor).
Route::middleware(['auth'])->group(function () {
    Volt::route('panel', 'panel')->name('panel');

    // Panel de supervisión por vendedor (admin/supervisor).
    Volt::route('equipo', 'team')
        ->middleware('permission:leads.view.all')
        ->name('team');

    Volt::route('leads', 'leads.index')->name('leads.index');

    Volt::route('leads/create', 'leads.create')
        ->middleware('permission:leads.capture')
        ->name('leads.create');

    Volt::route('leads/export', 'leads.export')
        ->middleware('permission:leads.export')
        ->name('leads.export');

    Route::get('leads/{lead}/attachments/{attachment}/download', [AttachmentController::class, 'download'])
        ->name('leads.attachments.download');

    Volt::route('leads/{lead}', 'leads.show')->name('leads.show');

    // Panel administrativo y reportes (§8).
    Volt::route('reportes', 'reports')
        ->middleware('permission:reports.view')
        ->name('reports');

    // Catálogos de configuración (§3.2).
    Route::middleware('permission:catalogs.manage')->group(function () {
        Volt::route('configuracion', 'catalogs.index')->name('catalogs.index');
        Volt::route('configuracion/ciudades', 'catalogs.cities')->name('catalogs.cities');
        Volt::route('configuracion/plantillas', 'catalogs.templates')->name('catalogs.templates');
        Volt::route('configuracion/catalogo/{type}', 'catalogs.manage')->name('catalogs.manage');
    });

    // Formularios web embebibles (captación web-to-lead).
    Route::middleware('permission:forms.manage')->group(function () {
        Volt::route('configuracion/formularios', 'catalogs.forms')->name('forms.index');
        Volt::route('configuracion/formularios/{form}', 'catalogs.form-edit')->name('forms.edit');
    });

    // Gestión de usuarios (§3.1).
    Volt::route('usuarios', 'users.index')
        ->middleware('permission:users.manage')
        ->name('users.index');
});

require __DIR__.'/auth.php';
