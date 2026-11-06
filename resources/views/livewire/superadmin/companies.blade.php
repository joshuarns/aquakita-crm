<?php

use App\Models\Company;
use App\Support\CompanyProvisioner;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showForm = false;

    public string $name = '';
    public string $brand_color = '#0e7490';
    public string $admin_name = '';
    public string $admin_email = '';
    public string $admin_password = '';

    /**
     * @return Collection<int, Company>
     */
    public function companies(): Collection
    {
        return Company::withCount(['users', 'leads'])->latest()->get();
    }

    public function create(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand_color' => ['required', 'string', 'max:20'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        $slug = Str::slug($data['name']);
        $base = $slug ?: 'empresa';
        $i = 1;
        while (Company::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        $company = Company::create([
            'name' => $data['name'],
            'slug' => $slug,
            'brand_color' => $data['brand_color'],
            'active' => true,
        ]);

        app(CompanyProvisioner::class)->provision($company, [
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => $data['admin_password'],
        ]);

        $this->reset('name', 'admin_name', 'admin_email', 'admin_password', 'showForm');
        $this->brand_color = '#0e7490';
        session()->flash('status', "Empresa «{$company->name}» creada con su administrador.");
    }

    public function toggle(int $id): void
    {
        $company = Company::findOrFail($id);
        $company->update(['active' => ! $company->active]);
    }
}; ?>

<div class="py-8">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#0891b2]">Plataforma</p>
                <h2 class="text-2xl font-bold text-gray-900">Empresas</h2>
                <p class="text-sm text-gray-500">Cada empresa tiene sus propios leads, usuarios y catálogos.</p>
            </div>
            <button wire:click="$toggle('showForm')"
                    class="rounded-lg bg-[#0e7490] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#155e75]">
                {{ $showForm ? 'Cancelar' : '+ Nueva empresa' }}
            </button>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        @if ($showForm)
            <form wire:submit="create" class="mb-6 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h3 class="font-semibold text-gray-800">Nueva empresa</h3>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre de la empresa</label>
                        <input type="text" wire:model="name" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Color de marca</label>
                        <input type="color" wire:model="brand_color" class="mt-1 h-10 w-16 rounded border-gray-300">
                    </div>
                </div>
                <h4 class="mt-6 text-sm font-semibold text-gray-700">Administrador de la empresa</h4>
                <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input type="text" wire:model="admin_name" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                        @error('admin_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Correo</label>
                        <input type="email" wire:model="admin_email" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                        @error('admin_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Contraseña</label>
                        <input type="text" wire:model="admin_password" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                        @error('admin_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <button type="submit" class="mt-5 rounded-lg bg-[#0e7490] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#155e75]">
                    Crear empresa
                </button>
            </form>
        @endif

        <div class="rounded-2xl border border-gray-100 bg-white shadow-sm overflow-hidden">
            @forelse ($this->companies() as $company)
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 last:border-0">
                    <div class="flex items-center gap-3">
                        <span class="h-8 w-8 rounded-lg" style="background: {{ $company->brand_color }}"></span>
                        <div>
                            <p class="font-medium text-gray-900">{{ $company->name }}</p>
                            <p class="text-sm text-gray-500">{{ $company->users_count }} usuarios · {{ $company->leads_count }} leads</p>
                        </div>
                    </div>
                    <button wire:click="toggle({{ $company->id }})"
                            class="rounded-full px-3 py-1 text-xs font-medium {{ $company->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $company->active ? 'Activa' : 'Inactiva' }}
                    </button>
                </div>
            @empty
                <div class="px-5 py-12 text-center text-gray-500">Aún no hay empresas.</div>
            @endforelse
        </div>
    </div>
</div>
