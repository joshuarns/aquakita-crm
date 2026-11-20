<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Public\LeadFormController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Models\Company;
use App\Support\Branding;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', 'dashboard');

// Logo de marca de la plataforma (el que configura el super-admin, o un placeholder «sin imagen»).
Route::get('brand/logo.png', fn () => response(Branding::logoBytes(), 200, [
    'Content-Type' => Branding::logoContentType(),
    'Cache-Control' => 'public, max-age=300',
]))->name('brand.logo');

// Logo por empresa (el propio, o un placeholder «sin imagen» si no tiene).
Route::get('c/{company}/logo.png', fn (Company $company) => response($company->logoBytes(), 200, [
    'Content-Type' => $company->logoContentType(),
    'Cache-Control' => 'public, max-age=300',
]))->name('company.logo');

// Webhook de WhatsApp Cloud API (público; se enruta por phone_number_id).
Route::get('webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('whatsapp.verify');
Route::post('webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->name('whatsapp.receive');

// Formularios web embebibles — endpoints públicos (sin autenticación).
Route::get('f/{token}.js', [LeadFormController::class, 'script'])->whereAlphaNumeric('token')->name('public.forms.script');
Route::get('f/{token}/widget.js', [LeadFormController::class, 'widget'])->whereAlphaNumeric('token')->name('public.forms.widget');
Route::get('f/{token}', [LeadFormController::class, 'show'])->whereAlphaNumeric('token')->name('public.forms.show');
Route::post('f/{token}', [LeadFormController::class, 'submit'])->whereAlphaNumeric('token')->name('public.forms.submit');

Volt::route('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Gestión de leads (§3.3 captura, §4.1 bandeja, §4.2 panel del vendedor).
// Plataforma (super-admin): alta y gestión de empresas.
Volt::route('superadmin/empresas', 'superadmin.companies')
    ->middleware(['auth', 'super_admin'])
    ->name('superadmin.companies');

// Plataforma (super-admin): marca de la instalación (logo/nombre del login y panel).
Volt::route('superadmin/marca', 'superadmin.branding')
    ->middleware(['auth', 'super_admin'])
    ->name('superadmin.branding');

Route::middleware(['auth'])->group(function () {
    Volt::route('panel', 'panel')->name('panel');

    // Panel de supervisión por vendedor (admin/supervisor).
    Volt::route('equipo', 'team')
        ->middleware('permission:leads.view.all')
        ->name('team');

    Volt::route('conversaciones', 'conversations.index')->name('conversations.index');

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
        Volt::route('configuracion/empresa', 'catalogs.company')->name('company.settings');
        Volt::route('configuracion/canales', 'catalogs.channels')->name('channels.settings');
        Volt::route('configuracion/ciudades', 'catalogs.cities')->name('catalogs.cities');
        Volt::route('configuracion/plantillas', 'catalogs.templates')->name('catalogs.templates');
        Volt::route('configuracion/catalogo/{type}', 'catalogs.manage')->name('catalogs.manage');
    });

    // Formularios web embebibles (captación web-to-lead).
    Route::middleware('permission:forms.manage')->group(function () {
        Volt::route('configuracion/formularios', 'catalogs.forms')->name('forms.index');
        Volt::route('configuracion/widgets', 'catalogs.widgets')->name('widgets.index');
        Volt::route('configuracion/formularios/{form}', 'catalogs.form-edit')->name('forms.edit');
    });

    // Gestión de usuarios (§3.1).
    Volt::route('usuarios', 'users.index')
        ->middleware('permission:users.manage')
        ->name('users.index');
});

require __DIR__.'/auth.php';
