<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php($company = auth()->user()?->company)

<div class="flex h-full flex-col text-slate-300">
    {{-- Marca --}}
    <div class="flex h-16 shrink-0 items-center gap-2.5 border-b border-white/10 px-5">
        <img src="{{ $company?->logoUrl() ?? route('brand.logo') }}" alt="" class="h-9 w-9 rounded-lg object-contain bg-white/10 p-1">
        <span class="truncate text-sm font-semibold text-white">{{ $company?->name ?? config('app.name') }}</span>
    </div>

    {{-- Navegación --}}
    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        @if (auth()->user()->is_super_admin)
            <a href="{{ route('superadmin.companies') }}" wire:navigate
               class="nav-link {{ request()->routeIs('superadmin.*') ? 'nav-link-active' : '' }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01"/></svg>
                Empresas
            </a>
        @else
            <p class="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">Operación</p>

            <a href="{{ route('dashboard') }}" wire:navigate class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-8 9 8M5 10v10h5v-6h4v6h5V10"/></svg>
                Dashboard
            </a>

            @role('vendedor')
                <a href="{{ route('panel') }}" wire:navigate class="nav-link {{ request()->routeIs('panel') ? 'nav-link-active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M4 12h16M4 19h10"/></svg>
                    Mi panel
                </a>
            @endrole

            @can('leads.view.all')
                <a href="{{ route('team') }}" wire:navigate class="nav-link {{ request()->routeIs('team') ? 'nav-link-active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.4-1.8M9 20H4v-2a3 3 0 015.4-1.8M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    Equipo
                </a>
            @endcan

            <a href="{{ route('leads.index') }}" wire:navigate
               class="nav-link {{ request()->routeIs('leads.index') || request()->routeIs('leads.create') || request()->routeIs('leads.show') ? 'nav-link-active' : '' }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 01-8 0M3 20a7 7 0 0118 0M5 7h.01"/><circle cx="12" cy="7" r="4"/></svg>
                Leads
            </a>

            @can('leads.export')
                <a href="{{ route('leads.export') }}" wire:navigate class="nav-link {{ request()->routeIs('leads.export') ? 'nav-link-active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                    Exportar
                </a>
            @endcan

            @can('reports.view')
                <p class="px-3 pb-1 pt-4 text-[10px] font-semibold uppercase tracking-wider text-slate-500">Análisis</p>
                <a href="{{ route('reports') }}" wire:navigate class="nav-link {{ request()->routeIs('reports') ? 'nav-link-active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 15l3-3 2 2 4-5"/></svg>
                    Reportes
                </a>
            @endcan

            @can('catalogs.manage')
                <p class="px-3 pb-1 pt-4 text-[10px] font-semibold uppercase tracking-wider text-slate-500">Ajustes</p>
                <a href="{{ route('catalogs.index') }}" wire:navigate class="nav-link {{ request()->routeIs('catalogs.*') || request()->routeIs('users.*') || request()->routeIs('forms.*') || request()->routeIs('widgets.*') || request()->routeIs('company.settings') ? 'nav-link-active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.3 4.3a2 2 0 013.4 0l.3.6a2 2 0 002.3 1l.7-.2a2 2 0 012.4 2.4l-.2.7a2 2 0 001 2.3l.6.3a2 2 0 010 3.4l-.6.3a2 2 0 00-1 2.3l.2.7a2 2 0 01-2.4 2.4l-.7-.2a2 2 0 00-2.3 1l-.3.6a2 2 0 01-3.4 0l-.3-.6a2 2 0 00-2.3-1l-.7.2a2 2 0 01-2.4-2.4l.2-.7a2 2 0 00-1-2.3l-.6-.3a2 2 0 010-3.4l.6-.3a2 2 0 001-2.3l-.2-.7a2 2 0 012.4-2.4l.7.2a2 2 0 002.3-1z"/><circle cx="12" cy="12" r="3"/></svg>
                    Configuración
                </a>
            @endcan
        @endif
    </nav>

    {{-- Pie: notificaciones, tema y usuario --}}
    <div class="shrink-0 border-t border-white/10 p-3">
        <div class="mb-2 flex items-center gap-2 px-1">
            @role('vendedor')
                <livewire:notifications.bell />
            @endrole
            <button type="button"
                    @click="document.documentElement.classList.toggle('dark'); localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light')"
                    class="ml-auto rounded-lg p-2 text-slate-400 hover:bg-white/10 hover:text-white" aria-label="Cambiar tema">
                <svg class="h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/></svg>
                <svg class="hidden h-5 w-5 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.4 6.4l-1.4-1.4M7 7L5.6 5.6m12.8 0L17 7M7 17l-1.4 1.4"/><circle cx="12" cy="12" r="4"/></svg>
            </button>
        </div>

        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left hover:bg-white/10">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-600 text-sm font-semibold text-white">
                    {{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-white" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
                    <span class="block truncate text-xs text-slate-400">{{ auth()->user()->email }}</span>
                </span>
                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4M8 15l4 4 4-4"/></svg>
            </button>

            <div x-show="open" @click.outside="open = false" x-transition x-cloak
                 class="absolute bottom-full left-0 mb-2 w-full overflow-hidden rounded-lg border border-slate-700 bg-slate-800 py-1 shadow-xl">
                <a href="{{ route('profile') }}" wire:navigate class="block px-4 py-2 text-sm text-slate-200 hover:bg-white/10">{{ __('Perfil') }}</a>
                <button wire:click="logout" class="block w-full px-4 py-2 text-left text-sm text-slate-200 hover:bg-white/10">{{ __('Cerrar sesión') }}</button>
            </div>
        </div>
    </div>
</div>
