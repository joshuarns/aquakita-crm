<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function sections(): array
    {
        return [
            // --- Catálogos ---
            ['group' => 'Catálogos', 'route' => 'catalogs.manage', 'param' => ['type' => 'sources'], 'title' => 'Fuentes de llegada', 'desc' => 'De dónde llegan los leads.', 'tone' => 'sky',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18l-7 8.5V20l-4-2v-5L3 4.5z"/>'],
            ['group' => 'Catálogos', 'route' => 'catalogs.manage', 'param' => ['type' => 'campaigns'], 'title' => 'Campañas', 'desc' => 'Campañas de marketing.', 'tone' => 'violet',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M11 5L6 9H3v6h3l5 4V5zM15.5 8.5a5 5 0 010 7M18.5 5.5a9 9 0 010 13"/>'],
            ['group' => 'Catálogos', 'route' => 'catalogs.manage', 'param' => ['type' => 'project_types'], 'title' => 'Tipos de proyecto', 'desc' => 'Clasificación del proyecto.', 'tone' => 'amber',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h5l9 9-7 7-9-9V7zM7.5 7.5h.01"/>'],
            ['group' => 'Catálogos', 'route' => 'catalogs.manage', 'param' => ['type' => 'discard_reasons'], 'title' => 'Motivos de descarte', 'desc' => 'Por qué se descarta un lead.', 'tone' => 'rose',
                'icon' => '<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.5 9.5l5 5M14.5 9.5l-5 5"/>'],
            ['group' => 'Catálogos', 'route' => 'catalogs.manage', 'param' => ['type' => 'languages'], 'title' => 'Idiomas', 'desc' => 'Idiomas de los prospectos.', 'tone' => 'indigo',
                'icon' => '<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/>'],
            ['group' => 'Catálogos', 'route' => 'catalogs.manage', 'param' => ['type' => 'countries'], 'title' => 'Países', 'desc' => 'Países de operación.', 'tone' => 'emerald',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.5 7-11a7 7 0 10-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>'],
            ['group' => 'Catálogos', 'route' => 'catalogs.cities', 'param' => [], 'title' => 'Ciudades', 'desc' => 'Ciudades por país.', 'tone' => 'sky',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V5a1 1 0 011-1h7a1 1 0 011 1v16M14 21V9h4a1 1 0 011 1v11M8 8h.01M8 12h.01M8 16h.01"/>'],

            // --- Comunicación ---
            ['group' => 'Comunicación', 'route' => 'catalogs.templates', 'param' => [], 'title' => 'Plantillas de correo', 'desc' => 'Textos de los avisos por correo.', 'tone' => 'teal',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>'],
            ['group' => 'Comunicación', 'route' => 'channels.settings', 'param' => [], 'title' => 'Canales de conversación', 'desc' => 'Correo y WhatsApp para responder a tus leads.', 'tone' => 'emerald',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12a8 8 0 01-11.6 7.1L3 21l1.9-6.4A8 8 0 1121 12z"/>'],

            // --- Empresa ---
            ['group' => 'Empresa', 'route' => 'company.settings', 'param' => [], 'title' => 'Mi empresa', 'desc' => 'Logo, nombre y color de tu marca.', 'tone' => 'slate',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2m-9 4h12M4 7h16a1 1 0 011 1v11a1 1 0 01-1 1H4a1 1 0 01-1-1V8a1 1 0 011-1z"/>'],
            ['group' => 'Empresa', 'route' => 'users.index', 'param' => [], 'title' => 'Usuarios', 'desc' => 'Alta, edición y permisos.', 'tone' => 'indigo',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.4-1.8M9 20H4v-2a3 3 0 015.4-1.8M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>'],
        ];
    }

    /**
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public function groups(): Collection
    {
        return collect($this->sections())->groupBy('group');
    }
}; ?>

@php
    $tones = [
        'sky' => 'bg-sky-50 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300',
        'violet' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300',
        'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
        'rose' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300',
        'indigo' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300',
        'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
        'teal' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300',
        'slate' => 'bg-slate-100 text-slate-600 dark:bg-slate-700/40 dark:text-slate-300',
    ];
@endphp

<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Configuración</h2>
    <p class="mb-2 text-sm text-muted">Catálogos, comunicación y ajustes de tu empresa.</p>

    @foreach ($this->groups() as $group => $items)
        <p class="eyebrow mb-3 mt-6">{{ $group }}</p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($items as $section)
                <a href="{{ route($section['route'], $section['param']) }}" wire:navigate
                   class="group flex items-center gap-4 card p-4 transition hover:-translate-y-0.5 hover:shadow-card-hover">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl {{ $tones[$section['tone']] }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">{!! $section['icon'] !!}</svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-slate-800 group-hover:text-brand-700 dark:text-slate-100 dark:group-hover:text-brand-400">{{ $section['title'] }}</p>
                        <p class="line-clamp-2 text-sm text-muted">{{ $section['desc'] }}</p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand-500 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endforeach
        </div>
    @endforeach
</div>
