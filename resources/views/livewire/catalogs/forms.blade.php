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
    public function forms(): Collection
    {
        return LeadForm::with('source')->withCount('leads')->latest()->get();
    }

    public function create(): void
    {
        $form = LeadForm::create([
            'name' => 'Formulario sin título',
            'token' => LeadForm::generateToken(),
            'fields' => LeadForm::defaultFields(),
            'accent_color' => '#0e7490',
            'active' => true,
        ]);

        $this->redirectRoute('forms.edit', $form, navigate: true);
    }

    public function toggle(int $id): void
    {
        $form = LeadForm::findOrFail($id);
        $form->update(['active' => ! $form->active]);
    }

    public function delete(int $id): void
    {
        LeadForm::findOrFail($id)->delete();

        session()->flash('status', 'Formulario eliminado.');
    }
}; ?>

<div class="py-8">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <a href="{{ route('catalogs.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; Configuración</a>
                <h2 class="text-xl font-semibold text-gray-800">Formularios web</h2>
            </div>
            <button wire:click="create"
                    class="inline-flex items-center gap-2 rounded-lg bg-[#0e7490] px-4 py-2 text-sm font-semibold text-white hover:bg-[#155e75]">
                + Nuevo formulario
            </button>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            @forelse ($this->forms() as $form)
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 last:border-0">
                    <div class="min-w-0">
                        <a href="{{ route('forms.edit', $form) }}" wire:navigate class="font-medium text-gray-900 hover:text-[#0e7490]">
                            {{ $form->name }}
                        </a>
                        <p class="text-sm text-gray-500">
                            {{ $form->source?->name ?? 'Sin origen' }} ·
                            {{ $form->leads_count }} {{ Str::plural('lead', $form->leads_count) }} ·
                            {{ $form->submissions_count }} envíos
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button wire:click="toggle({{ $form->id }})"
                                class="rounded-full px-3 py-1 text-xs font-medium {{ $form->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $form->active ? 'Activo' : 'Inactivo' }}
                        </button>
                        <a href="{{ route('forms.edit', $form) }}" wire:navigate class="text-sm text-[#0e7490] hover:underline">Editar</a>
                        <button wire:click="delete({{ $form->id }})"
                                wire:confirm="¿Eliminar este formulario? Los leads ya capturados no se borran."
                                class="text-sm text-red-600 hover:underline">Eliminar</button>
                    </div>
                </div>
            @empty
                <div class="px-5 py-12 text-center text-gray-500">
                    <p>Aún no tienes formularios.</p>
                    <p class="text-sm mt-1">Crea uno para empezar a captar leads desde tu sitio web.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
