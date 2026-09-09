<?php

use App\Models\EmailTemplate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $editingId = null;

    public string $subject = '';

    public string $body = '';

    #[Computed]
    public function templates()
    {
        return EmailTemplate::orderBy('key')->get();
    }

    public function edit(int $id): void
    {
        $template = EmailTemplate::findOrFail($id);
        $this->editingId = $id;
        $this->subject = $template->subject;
        $this->body = $template->body;
    }

    public function save(): void
    {
        $this->authorize('catalogs.manage');

        $data = $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        EmailTemplate::findOrFail($this->editingId)->update($data);

        $this->reset(['editingId', 'subject', 'body']);
        session()->flash('template_status', 'Plantilla actualizada.');
    }

    public function toggle(int $id): void
    {
        $this->authorize('catalogs.manage');
        $template = EmailTemplate::findOrFail($id);
        $template->update(['active' => ! $template->active]);
    }
}; ?>

<div class="py-8">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">{{ __('Plantillas de correo') }}</h2>
            <a href="{{ route('catalogs.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">
                &larr; {{ __('Configuración') }}
            </a>
        </div>

        @if (session('template_status'))
            <div class="mb-4 rounded-md border border-green-300 bg-green-50 p-3 text-sm text-green-800">{{ session('template_status') }}</div>
        @endif

        @if ($editingId)
            <form wire:submit="save" class="bg-white shadow-sm sm:rounded-lg p-5 mb-6 space-y-4">
                <p class="text-xs text-gray-500">
                    {{ __('Marcadores disponibles:') }}
                    <code>@{{vendedor}}</code>, <code>@{{lead}}</code>, <code>@{{fecha}}</code>
                </p>
                <div>
                    <x-input-label :value="__('Asunto')" />
                    <x-text-input wire:model="subject" class="block mt-1 w-full text-sm" type="text" />
                    <x-input-error :messages="$errors->get('subject')" class="mt-1" />
                </div>
                <div>
                    <x-input-label :value="__('Cuerpo')" />
                    <textarea wire:model="body" rows="4" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                    <x-input-error :messages="$errors->get('body')" class="mt-1" />
                </div>
                <div class="flex gap-2">
                    <x-primary-button>{{ __('Guardar') }}</x-primary-button>
                    <button type="button" wire:click="$set('editingId', null)" class="text-sm text-gray-600 hover:text-gray-900 px-3">{{ __('Cancelar') }}</button>
                </div>
            </form>
        @endif

        <div class="space-y-3">
            @foreach ($this->templates as $template)
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <div class="flex items-start justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-mono text-gray-400">{{ $template->key }}</p>
                            <p class="font-medium text-gray-900 mt-0.5">{{ $template->subject }}</p>
                            <p class="text-sm text-gray-600 mt-1">{{ $template->body }}</p>
                        </div>
                        <div class="flex flex-col items-end gap-2 shrink-0 ml-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $template->active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $template->active ? __('Activa') : __('Inactiva') }}
                            </span>
                            <div class="flex gap-3">
                                <button wire:click="edit({{ $template->id }})" class="text-indigo-600 hover:text-indigo-800 text-xs">{{ __('Editar') }}</button>
                                <button wire:click="toggle({{ $template->id }})" class="text-gray-600 hover:text-gray-900 text-xs">
                                    {{ $template->active ? __('Desactivar') : __('Activar') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
