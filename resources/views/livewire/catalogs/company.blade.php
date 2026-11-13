<?php

use App\Models\Company;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public Company $company;

    public string $name = '';
    public string $brand_color = '#0e7490';
    public string $timezone = 'America/Mexico_City';
    public string $locale = 'es';
    public string $from_email = '';
    public $logoFile = null;

    public function mount(): void
    {
        $this->company = auth()->user()->company;
        abort_if($this->company === null, 404);

        $this->name = $this->company->name;
        $this->brand_color = $this->company->brand_color ?: '#0e7490';
        $this->timezone = $this->company->timezone ?: 'America/Mexico_City';
        $this->locale = $this->company->locale ?: 'es';
        $this->from_email = (string) $this->company->from_email;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand_color' => ['required', 'string', 'max:20'],
            'timezone' => ['required', 'string', 'max:64'],
            'locale' => ['in:es,en,pt'],
            'from_email' => ['nullable', 'email', 'max:255'],
            'logoFile' => ['nullable', 'image', 'max:1024'], // 1 MB
        ]);

        $data = [
            'name' => $this->name,
            'brand_color' => $this->brand_color,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'from_email' => $this->from_email ?: null,
        ];

        if ($this->logoFile) {
            $data['logo'] = base64_encode(file_get_contents($this->logoFile->getRealPath()));
        }

        $this->company->update($data);
        $this->logoFile = null;

        session()->flash('status', 'Datos de la empresa guardados.');
    }
}; ?>

<div class="py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ route('catalogs.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; Configuración</a>
        <h2 class="mt-1 text-xl font-semibold text-gray-800">Mi empresa</h2>
        <p class="text-sm text-gray-500 mb-6">Tu marca en correos, formularios y el sistema.</p>

        @if (session('status'))
            <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <form wire:submit="save" class="space-y-6 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-4">
                <img src="{{ $company->logoUrl() }}" alt="" class="h-16 w-16 rounded-xl object-contain bg-gray-50 border border-gray-100">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Logo (PNG/JPG, máx. 1 MB)</label>
                    <input type="file" wire:model="logoFile" accept="image/*" class="mt-1 block text-sm text-gray-600">
                    <div wire:loading wire:target="logoFile" class="text-xs text-gray-400">Subiendo…</div>
                    @error('logoFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nombre de la empresa</label>
                    <input type="text" wire:model="name" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Color de marca</label>
                    <input type="color" wire:model="brand_color" class="mt-1 h-10 w-16 rounded border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Zona horaria</label>
                    <input type="text" wire:model="timezone" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Idioma</label>
                    <select wire:model="locale" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                        <option value="es">Español</option>
                        <option value="en">English</option>
                        <option value="pt">Português</option>
                    </select>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-5">
                <label class="block text-sm font-medium text-gray-700">Correo del remitente (opcional)</label>
                <input type="email" wire:model="from_email" placeholder="{{ config('mail.from.address') }}"
                       class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                @error('from_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-gray-500">
                    Los correos salen con el <strong>nombre de tu empresa</strong>. Aquí puedes poner también tu propio correo «De».
                    <span class="text-amber-700">Importante:</span> el dominio de ese correo debe autorizar a este servidor (registro SPF); si no, los correos pueden caer en spam.
                    Déjalo vacío para usar el correo de la plataforma ({{ config('mail.from.address') }}).
                </p>
            </div>

            <button type="submit" class="rounded-lg bg-[#0e7490] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#155e75]">
                Guardar cambios
            </button>
        </form>
    </div>
</div>
