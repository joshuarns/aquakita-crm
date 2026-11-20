<?php

use App\Support\Branding;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public string $brand_name = '';
    public string $brand_tagline = '';
    public $logoFile = null;

    public function mount(): void
    {
        $this->brand_name = (string) Branding::get('brand_name');
        $this->brand_tagline = (string) Branding::get('brand_tagline');
    }

    public function save(): void
    {
        $this->validate([
            'brand_name' => ['nullable', 'string', 'max:60'],
            'brand_tagline' => ['nullable', 'string', 'max:120'],
            'logoFile' => ['nullable', 'image', 'max:1024'], // 1 MB
        ]);

        Branding::set('brand_name', $this->brand_name ?: null);
        Branding::set('brand_tagline', $this->brand_tagline ?: null);

        if ($this->logoFile) {
            Branding::set('brand_logo', base64_encode(file_get_contents($this->logoFile->getRealPath())));
            $this->logoFile = null;
        }

        session()->flash('status', 'Marca de la plataforma actualizada.');
    }

    public function removeLogo(): void
    {
        Branding::set('brand_logo', null);
        session()->flash('status', 'Logo restablecido al de la plataforma.');
    }

    public function with(): array
    {
        return [
            'hasLogo' => Branding::hasLogo(),
            'logoSrc' => route('brand.logo').'?v='.now()->timestamp,
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
    <a href="{{ route('superadmin.companies') }}" wire:navigate
       class="inline-flex items-center gap-1.5 text-sm font-medium text-muted hover:text-slate-700 dark:hover:text-slate-200">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Empresas
    </a>
    <p class="eyebrow mt-3">Plataforma</p>
    <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Marca</h2>
    <p class="mb-6 text-sm text-muted">El logo y el nombre que se ven en la pantalla de inicio de sesión y en el panel del super-admin.</p>

    @if (session('status'))
        <div class="mb-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-300">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="save" class="card card-pad space-y-6">
        <div class="flex flex-wrap items-center gap-4">
            <img src="{{ $logoSrc }}" alt="" class="h-16 w-16 rounded-xl border border-slate-100 object-contain surface-muted dark:border-slate-800">
            <div class="min-w-0 flex-1">
                <label class="label">Logo (PNG/JPG, máx. 1 MB)</label>
                <input type="file" wire:model="logoFile" accept="image/*" class="mt-1 block text-sm text-slate-600 dark:text-slate-300">
                <div wire:loading wire:target="logoFile" class="text-xs text-muted">Subiendo…</div>
                @error('logoFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @if ($hasLogo)
                    <button type="button" wire:click="removeLogo" class="mt-2 text-xs font-medium text-red-600 hover:text-red-700">Quitar logo (usar el de la plataforma)</button>
                @endif
            </div>
        </div>

        <div>
            <label class="label">Nombre de la plataforma</label>
            <input type="text" wire:model="brand_name" maxlength="60" placeholder="{{ config('app.name') }}" class="input">
            @error('brand_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-muted">Se muestra junto al logo en el panel. Vacío = «{{ config('app.name') }}».</p>
        </div>

        <div>
            <label class="label">Lema del login (opcional)</label>
            <input type="text" wire:model="brand_tagline" maxlength="120" placeholder="Sistema comercial {{ config('app.name') }}" class="input">
            @error('brand_tagline') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-muted">Texto pequeño al pie de la pantalla de inicio de sesión.</p>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">Guardar marca</span>
                <span wire:loading wire:target="save">Guardando…</span>
            </button>
        </div>
    </form>
</div>
