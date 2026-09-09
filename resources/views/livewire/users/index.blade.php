<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = '';

    #[Computed]
    public function users()
    {
        return User::with('roles')->orderBy('name')->get();
    }

    #[Computed]
    public function roles()
    {
        return Role::orderBy('name')->pluck('name');
    }

    public function startCreate(): void
    {
        $this->reset(['editingId', 'name', 'email', 'password', 'role']);
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->editingId = $id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->roles->first()?->name ?? '';
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'email', 'password', 'role']);
    }

    public function save(): void
    {
        $this->authorize('users.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'role' => ['required', Rule::in($this->roles()->all())],
            // La contraseña es obligatoria al crear; opcional al editar (§3.1).
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8'],
        ]);

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $user->update(['name' => $validated['name'], 'email' => $validated['email']]);
            if ($validated['password']) {
                $user->update(['password' => Hash::make($validated['password'])]);
            }
        } else {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'active' => true,
            ]);
        }

        $user->syncRoles([$validated['role']]);

        $this->cancel();
        session()->flash('user_status', 'Usuario guardado.');
    }

    /**
     * Activa o desactiva un usuario sin borrar su historial (§2).
     * No permite desactivarse a uno mismo.
     */
    public function toggle(int $id): void
    {
        $this->authorize('users.manage');

        if ($id === Auth::id()) {
            session()->flash('user_status', 'No puedes desactivar tu propia cuenta.');

            return;
        }

        $user = User::findOrFail($id);
        $user->update(['active' => ! $user->active]);
    }
}; ?>

<div class="py-8">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">{{ __('Usuarios') }}</h2>
            <a href="{{ route('catalogs.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">
                &larr; {{ __('Configuración') }}
            </a>
        </div>

        @if (session('user_status'))
            <div class="mb-4 rounded-md border border-green-300 bg-green-50 p-3 text-sm text-green-800">{{ session('user_status') }}</div>
        @endif

        <form wire:submit="save" class="bg-white shadow-sm sm:rounded-lg p-5 mb-6">
            <p class="text-sm font-semibold text-gray-700 mb-3">{{ $editingId ? __('Editar usuario') : __('Nuevo usuario') }}</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input-label :value="__('Nombre')" />
                    <x-text-input wire:model="name" class="block mt-1 w-full text-sm" type="text" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <x-input-label :value="__('Correo')" />
                    <x-text-input wire:model="email" class="block mt-1 w-full text-sm" type="email" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>
                <div>
                    <x-input-label :value="__('Rol')" />
                    <select wire:model="role" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">{{ __('— Selecciona —') }}</option>
                        @foreach ($this->roles as $r)
                            <option value="{{ $r }}">{{ ucfirst($r) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('role')" class="mt-1" />
                </div>
                <div>
                    <x-input-label :value="$editingId ? __('Contraseña (dejar en blanco para no cambiar)') : __('Contraseña')" />
                    <x-text-input wire:model="password" class="block mt-1 w-full text-sm" type="password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-1" />
                </div>
            </div>
            <div class="flex gap-2 mt-4">
                <x-primary-button>{{ $editingId ? __('Guardar') : __('Crear usuario') }}</x-primary-button>
                @if ($editingId)
                    <button type="button" wire:click="cancel" class="text-sm text-gray-600 hover:text-gray-900 px-3">{{ __('Cancelar') }}</button>
                @endif
            </div>
        </form>

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <th class="px-4 py-3">{{ __('Nombre') }}</th>
                        <th class="px-4 py-3">{{ __('Correo') }}</th>
                        <th class="px-4 py-3">{{ __('Rol') }}</th>
                        <th class="px-4 py-3">{{ __('Estado') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($this->users as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-900">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ ucfirst($user->roles->first()?->name ?? '—') }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $user->active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $user->active ? __('Activo') : __('Inactivo') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button wire:click="edit({{ $user->id }})" class="text-indigo-600 hover:text-indigo-800 text-xs">{{ __('Editar') }}</button>
                                @if ($user->id !== auth()->id())
                                    <button wire:click="toggle({{ $user->id }})" class="text-gray-600 hover:text-gray-900 text-xs ml-3">
                                        {{ $user->active ? __('Desactivar') : __('Activar') }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
