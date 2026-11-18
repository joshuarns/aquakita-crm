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

<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
    <a href="{{ route('catalogs.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300">&larr; Configuración</a>
    <h2 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">Mi empresa</h2>
    <p class="mb-6 text-sm text-muted">Tu marca en correos, formularios y el sistema.</p>

    @if (session('status'))
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="save" class="card space-y-6 p-6">
        <div class="flex items-center gap-4">
            <img src="{{ $company->logoUrl() }}" alt="" class="h-16 w-16 rounded-xl border border-slate-100 object-contain surface-muted dark:border-slate-800">
            <div>
                <label class="label">Logo (PNG/JPG, máx. 1 MB)</label>
                <input type="file" wire:model="logoFile" accept="image/*" class="mt-1 block text-sm text-slate-600 dark:text-slate-300">
                <div wire:loading wire:target="logoFile" class="text-xs text-muted">Subiendo…</div>
                @error('logoFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Nombre de la empresa</label>
                <input type="text" wire:model="name" class="input">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Color de marca</label>
                <input type="color" wire:model="brand_color" class="mt-1 h-10 w-16 rounded border-slate-300 dark:border-slate-700">
            </div>
            <div>
                <label class="label">Zona horaria</label>
                <input type="text" wire:model="timezone" class="input">
            </div>
            <div>
                <label class="label">Idioma</label>
                <select wire:model="locale" class="select">
                    <option value="es">Español</option>
                    <option value="en">English</option>
                    <option value="pt">Português</option>
                </select>
            </div>
        </div>

        <div class="border-t border-slate-100 pt-5 dark:border-slate-800">
            <label class="label">Correo del remitente (opcional)</label>
            <input type="email" wire:model="from_email" placeholder="{{ config('mail.from.address') }}" class="input">
            @error('from_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-muted">
                Los correos salen con el <strong>nombre de tu empresa</strong>. Aquí puedes poner tu propio correo «De».
                <span class="text-amber-700 dark:text-amber-400">Importante:</span> el dominio debe autorizar este servidor (SPF) o pueden caer en spam.
                Vacío = usa el correo de la plataforma ({{ config('mail.from.address') }}).
            </p>
        </div>

        <button type="submit" class="btn-primary">Guardar cambios</button>
    </form>
</div>
