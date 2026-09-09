<?php

use App\Models\City;
use App\Models\Country;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $filterCountry = null;

    public ?int $editingId = null;

    public ?int $country_id = null;

    public string $name = '';

    #[Computed]
    public function countries()
    {
        return Country::orderBy('name')->get();
    }

    #[Computed]
    public function cities()
    {
        return City::with('country')
            ->when($this->filterCountry, fn ($q) => $q->where('country_id', $this->filterCountry))
            ->orderBy('name')
            ->get();
    }

    public function edit(int $id): void
    {
        $city = City::findOrFail($id);
        $this->editingId = $id;
        $this->country_id = $city->country_id;
        $this->name = $city->name;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'country_id', 'name']);
    }

    public function save(): void
    {
        $this->authorize('catalogs.manage');

        $data = $this->validate([
            'country_id' => ['required', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($this->editingId) {
            City::findOrFail($this->editingId)->update($data);
            $message = 'Ciudad actualizada.';
        } else {
            City::create($data + ['active' => true]);
            $message = 'Ciudad agregada.';
        }

        $this->cancel();
        session()->flash('city_status', $message);
    }

    public function toggle(int $id): void
    {
        $this->authorize('catalogs.manage');
        $city = City::findOrFail($id);
        $city->update(['active' => ! $city->active]);
    }
}; ?>

<div class="py-8">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">{{ __('Ciudades') }}</h2>
            <a href="{{ route('catalogs.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">
                &larr; {{ __('Configuración') }}
            </a>
        </div>

        @if (session('city_status'))
            <div class="mb-4 rounded-md border border-green-300 bg-green-50 p-3 text-sm text-green-800">{{ session('city_status') }}</div>
        @endif

        {{-- Alta / edición --}}
        <form wire:submit="save" class="bg-white shadow-sm sm:rounded-lg p-5 mb-6">
            <div class="flex flex-col sm:flex-row gap-3 items-end">
                <div class="flex-1 w-full">
                    <x-input-label :value="__('País')" />
                    <select wire:model="country_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">{{ __('— Selecciona —') }}</option>
                        @foreach ($this->countries as $country)
                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('country_id')" class="mt-1" />
                </div>
                <div class="flex-1 w-full">
                    <x-input-label :value="__('Ciudad')" />
                    <x-text-input wire:model="name" class="block mt-1 w-full text-sm" type="text" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div class="flex gap-2">
                    <x-primary-button>{{ $editingId ? __('Guardar') : __('Agregar') }}</x-primary-button>
                    @if ($editingId)
                        <button type="button" wire:click="cancel" class="text-sm text-gray-600 hover:text-gray-900 px-3">{{ __('Cancelar') }}</button>
                    @endif
                </div>
            </div>
        </form>

        {{-- Filtro por país --}}
        <div class="mb-3">
            <select wire:model.live="filterCountry" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                <option value="">{{ __('Todos los países') }}</option>
                @foreach ($this->countries as $country)
                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Listado --}}
        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <th class="px-4 py-3">{{ __('Ciudad') }}</th>
                        <th class="px-4 py-3">{{ __('País') }}</th>
                        <th class="px-4 py-3">{{ __('Estado') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->cities as $city)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-900">{{ $city->name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $city->country?->name }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $city->active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $city->active ? __('Activa') : __('Inactiva') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button wire:click="edit({{ $city->id }})" class="text-indigo-600 hover:text-indigo-800 text-xs">{{ __('Editar') }}</button>
                                <button wire:click="toggle({{ $city->id }})" class="text-gray-600 hover:text-gray-900 text-xs ml-3">
                                    {{ $city->active ? __('Desactivar') : __('Activar') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">{{ __('Sin ciudades.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
