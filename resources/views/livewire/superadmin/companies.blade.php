<?php

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
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
        return Company::withCount([
            'users',
            'leads',
            'leads as leads_month_count' => fn ($q) => $q->where('created_at', '>=', now()->startOfMonth()),
        ])->latest()->get();
    }

    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        return [
            'total' => Company::count(),
            'active' => Company::where('active', true)->count(),
            'users' => User::withoutGlobalScopes()->whereNotNull('company_id')->count(),
            'leads' => Lead::withoutGlobalScopes()->count(),
        ];
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

    // --- Eliminar empresa (destructivo: borra todo su contenido en cascada) ---
    public ?int $deletingId = null;

    public string $deleteConfirm = '';

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->deleteConfirm = '';
        $this->resetErrorBag('deleteConfirm');
    }

    public function cancelDelete(): void
    {
        $this->reset('deletingId', 'deleteConfirm');
    }

    public function destroy(): void
    {
        $company = Company::findOrFail($this->deletingId);

        if (trim($this->deleteConfirm) !== $company->name) {
            $this->addError('deleteConfirm', 'El nombre no coincide. Escríbelo exactamente para confirmar.');

            return;
        }

        $name = $company->name;
        $company->delete(); // cascada: usuarios, leads, catálogos, formularios, conversaciones…

        $this->reset('deletingId', 'deleteConfirm');
        session()->flash('status', "Empresa «{$name}» eliminada permanentemente.");
    }

    /**
     * Primer usuario (administrador) de la empresa, para mostrar el contacto.
     */
    public function adminOf(Company $company): ?User
    {
        return User::withoutGlobalScopes()->where('company_id', $company->id)->orderBy('id')->first();
    }
}; ?>

<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow">Plataforma</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">Empresas</h2>
            <p class="text-sm text-muted">Cada empresa tiene sus propios leads, usuarios y catálogos.</p>
        </div>
        <button wire:click="$toggle('showForm')" class="{{ $showForm ? 'btn-secondary' : 'btn-primary' }}">
            @if (! $showForm)
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            @endif
            {{ $showForm ? 'Cancelar' : 'Nueva empresa' }}
        </button>
    </div>

    {{-- KPIs de la plataforma --}}
    @php($stats = $this->stats())
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Empresas', 'value' => $stats['total'], 'tone' => 'bg-brand-700/10 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300', 'icon' => 'M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M9 13h.01M15 9h.01M15 13h.01'],
            ['label' => 'Activas', 'value' => $stats['active'], 'tone' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Usuarios', 'value' => $stats['users'], 'tone' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300', 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z'],
            ['label' => 'Leads', 'value' => $stats['leads'], 'tone' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300', 'icon' => 'M4 19V5m0 14h16M8 15l3-3 2 2 4-5'],
        ] as $kpi)
            <div class="card card-pad">
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $kpi['tone'] }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $kpi['icon'] }}"/></svg>
                    </span>
                    <div>
                        <p class="text-2xl font-bold leading-none text-slate-900 dark:text-white">{{ number_format($kpi['value']) }}</p>
                        <p class="mt-1 text-xs font-medium text-muted">{{ $kpi['label'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if (session('status'))
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            {{ session('status') }}
        </div>
    @endif

    @if ($showForm)
        <form wire:submit="create" class="card mb-6 space-y-5 p-6">
            <h3 class="font-semibold text-slate-800 dark:text-slate-100">Nueva empresa</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label class="label">Nombre de la empresa</label>
                    <input type="text" wire:model="name" class="input">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Color de marca</label>
                    <input type="color" wire:model="brand_color" class="mt-1 h-10 w-16 rounded border-slate-300 dark:border-slate-700">
                </div>
            </div>
            <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Administrador de la empresa</h4>
                <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label">Nombre</label>
                        <input type="text" wire:model="admin_name" class="input">
                        @error('admin_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Correo</label>
                        <input type="email" wire:model="admin_email" class="input">
                        @error('admin_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Contraseña</label>
                        <input type="text" wire:model="admin_password" class="input">
                        @error('admin_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
            <button type="submit" class="btn-primary">Crear empresa</button>
        </form>
    @endif

    <div class="space-y-3">
        @forelse ($this->companies() as $company)
            <div class="card flex flex-wrap items-center gap-4 p-4 transition hover:shadow-card-hover">
                {{-- Logo / marca --}}
                @if ($company->logo)
                    <img src="{{ $company->logoUrl() }}" alt="" class="h-12 w-12 shrink-0 rounded-xl border border-slate-100 object-contain surface-muted dark:border-slate-800">
                @else
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl text-lg font-bold text-white" style="background: {{ $company->brandColor() }}">
                        {{ Str::upper(Str::substr($company->name, 0, 1)) }}
                    </span>
                @endif

                {{-- Identidad --}}
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="truncate font-semibold text-slate-900 dark:text-slate-100">{{ $company->name }}</p>
                        <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $company->brandColor() }}"></span>
                    </div>
                    <p class="truncate text-xs text-muted">{{ $company->slug }} · creada {{ $company->created_at?->diffForHumans() }}</p>
                    @php($admin = $this->adminOf($company))
                    @if ($admin)
                        <p class="mt-0.5 flex items-center gap-1 truncate text-xs text-slate-500 dark:text-slate-400">
                            <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 20a7 7 0 0116 0"/></svg>
                            <span class="truncate">{{ $admin->name }} · {{ $admin->email }}</span>
                        </p>
                    @endif

                    {{-- Canales configurados --}}
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        @if ($company->conversations_enabled)
                            <span class="badge-brand text-[10px]">Correo</span>
                        @endif
                        @if ($company->whatsapp_enabled)
                            <span class="badge-success text-[10px]">WhatsApp</span>
                        @endif
                        @if (! $company->conversations_enabled && ! $company->whatsapp_enabled)
                            <span class="badge-neutral text-[10px]">Sin canales</span>
                        @endif
                    </div>
                </div>

                {{-- Métricas --}}
                <div class="flex items-center gap-5 text-center">
                    <div>
                        <p class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ $company->users_count }}</p>
                        <p class="text-[11px] text-muted">usuarios</p>
                    </div>
                    <div>
                        <p class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ $company->leads_count }}</p>
                        <p class="text-[11px] text-muted">leads</p>
                    </div>
                    <div>
                        <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">+{{ $company->leads_month_count }}</p>
                        <p class="text-[11px] text-muted">este mes</p>
                    </div>
                </div>

                {{-- Estado --}}
                <button wire:click="toggle({{ $company->id }})"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition {{ $company->active ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $company->active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                    {{ $company->active ? 'Activa' : 'Inactiva' }}
                </button>

                <button wire:click="confirmDelete({{ $company->id }})" class="btn-danger-ghost btn-sm" title="Eliminar empresa">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m-7 0v12a1 1 0 001 1h6a1 1 0 001-1V7"/></svg>
                </button>
            </div>
        @empty
            <div class="card p-12 text-center">
                <svg class="mx-auto h-10 w-10 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14"/></svg>
                <p class="mt-3 text-sm text-muted">Aún no hay empresas. Crea la primera con «Nueva empresa».</p>
            </div>
        @endforelse
    </div>

    {{-- Modal: eliminar empresa (type-to-confirm) --}}
    @if ($deletingId)
        @php($del = \App\Models\Company::withCount(['leads', 'users'])->find($deletingId))
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="cancelDelete"></div>
            <div class="relative w-full max-w-md card p-6">
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 4.3L2.6 18a1.5 1.5 0 001.3 2.2h16.2a1.5 1.5 0 001.3-2.2L13.7 4.3a1.5 1.5 0 00-2.6 0z"/></svg>
                    </span>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Eliminar empresa</h3>
                </div>
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                    Vas a eliminar <strong>{{ $del?->name }}</strong> y <strong>todo</strong> su contenido:
                    <strong>{{ $del?->users_count }}</strong> usuarios, <strong>{{ $del?->leads_count }}</strong> leads,
                    catálogos, formularios y conversaciones.
                    <span class="font-medium text-red-600 dark:text-red-400">Esta acción no se puede deshacer.</span>
                </p>
                <div class="mt-4">
                    <label class="label">Escribe «{{ $del?->name }}» para confirmar</label>
                    <input type="text" wire:model="deleteConfirm" class="input" autocomplete="off" wire:keydown.enter="destroy">
                    @error('deleteConfirm') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDelete" class="btn-secondary">Cancelar</button>
                    <button type="button" wire:click="destroy" class="btn-danger">Eliminar definitivamente</button>
                </div>
            </div>
        </div>
    @endif
</div>
