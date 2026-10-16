<?php

use App\Models\LeadForm;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    /**
     * @return Collection<int, LeadForm>
     */
    public function widgets(): Collection
    {
        return LeadForm::where('display_mode', 'floating')
            ->with('source')->withCount('leads')->latest()->get();
    }

    public function create(): void
    {
        $widget = LeadForm::create([
            'name' => 'Widget sin título',
            'token' => LeadForm::generateToken(),
            'fields' => LeadForm::defaultFields(),
            'accent_color' => '#0e7490',
            'display_mode' => 'floating',
            'launcher_label' => '¿En qué podemos ayudarte?',
            'launcher_position' => 'right',
            'active' => true,
        ]);

        $this->redirectRoute('forms.edit', $widget, navigate: true);
    }

    public function toggle(int $id): void
    {
        $widget = LeadForm::findOrFail($id);
        $widget->update(['active' => ! $widget->active]);
    }

    public function delete(int $id): void
    {
        LeadForm::findOrFail($id)->delete();

        session()->flash('status', 'Widget eliminado.');
    }
}; ?>

<div class="py-8">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <a href="{{ route('catalogs.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; Configuración</a>
                <h2 class="text-xl font-semibold text-gray-800">Widgets flotantes</h2>
                <p class="text-sm text-gray-500">Botón flotante que abre un formulario en el sitio y avisa por WhatsApp.</p>
            </div>
            <button wire:click="create"
                    class="inline-flex items-center gap-2 rounded-lg bg-[#0e7490] px-4 py-2 text-sm font-semibold text-white hover:bg-[#155e75]">
                + Nuevo widget
            </button>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            @forelse ($this->widgets() as $widget)
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 last:border-0">
                    <div class="min-w-0">
                        <a href="{{ route('forms.edit', $widget) }}" wire:navigate class="font-medium text-gray-900 hover:text-[#0e7490]">
                            <span class="mr-1">{{ $widget->launcher_icon ?: '💬' }}</span>{{ $widget->name }}
                        </a>
                        <p class="text-sm text-gray-500">
                            {{ $widget->leads_count }} {{ Str::plural('lead', $widget->leads_count) }} ·
                            {{ $widget->submissions_count }} envíos ·
                            WhatsApp: {{ $widget->whatsapp_number ?: 'sin configurar' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button wire:click="toggle({{ $widget->id }})"
                                class="rounded-full px-3 py-1 text-xs font-medium {{ $widget->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $widget->active ? 'Activo' : 'Inactivo' }}
                        </button>
                        <a href="{{ route('forms.edit', $widget) }}" wire:navigate class="text-sm text-[#0e7490] hover:underline">Editar</a>
                        <button wire:click="delete({{ $widget->id }})"
                                wire:confirm="¿Eliminar este widget? Los leads ya capturados no se borran."
                                class="text-sm text-red-600 hover:underline">Eliminar</button>
                    </div>
                </div>
            @empty
                <div class="px-5 py-12 text-center text-gray-500">
                    <p>Aún no tienes widgets flotantes.</p>
                    <p class="text-sm mt-1">Crea uno para captar leads con un botón flotante en tu sitio.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
