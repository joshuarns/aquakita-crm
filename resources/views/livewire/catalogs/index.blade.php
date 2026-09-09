<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    /**
     * @return array<int, array{route: string, param: array<string, string>, title: string, desc: string}>
     */
    public function sections(): array
    {
        return [
            ['route' => 'catalogs.manage', 'param' => ['type' => 'sources'], 'title' => 'Fuentes de llegada', 'desc' => 'De dónde llegan los leads.'],
            ['route' => 'catalogs.manage', 'param' => ['type' => 'campaigns'], 'title' => 'Campañas', 'desc' => 'Campañas de marketing.'],
            ['route' => 'catalogs.manage', 'param' => ['type' => 'project_types'], 'title' => 'Tipos de proyecto', 'desc' => 'Clasificación del proyecto.'],
            ['route' => 'catalogs.manage', 'param' => ['type' => 'discard_reasons'], 'title' => 'Motivos de descarte', 'desc' => 'Por qué se descarta un lead.'],
            ['route' => 'catalogs.manage', 'param' => ['type' => 'languages'], 'title' => 'Idiomas', 'desc' => 'Idiomas de los prospectos.'],
            ['route' => 'catalogs.manage', 'param' => ['type' => 'countries'], 'title' => 'Países', 'desc' => 'Países de operación.'],
            ['route' => 'catalogs.templates', 'param' => [], 'title' => 'Plantillas de correo', 'desc' => 'Textos de los avisos por correo.'],
            ['route' => 'users.index', 'param' => [], 'title' => 'Usuarios', 'desc' => 'Alta, edición y permisos.'],
        ];
    }
}; ?>

<div class="py-8">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
        <h2 class="text-xl font-semibold text-gray-800 mb-6">{{ __('Configuración') }}</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($this->sections() as $section)
                <a href="{{ route($section['route'], $section['param']) }}" wire:navigate
                   class="block bg-white shadow-sm rounded-lg p-5 hover:shadow-md transition">
                    <p class="font-semibold text-gray-800">{{ $section['title'] }}</p>
                    <p class="text-sm text-gray-500 mt-1">{{ $section['desc'] }}</p>
                </a>
            @endforeach
        </div>
    </div>
</div>
