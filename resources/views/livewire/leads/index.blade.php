<?php

use App\Models\Lead;
use App\Models\Status;
use App\Models\User;
use App\Support\LeadNotifier;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $statusFilter = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Bandeja respetando la separación estricta por rol (§2, §4.1, §4.2).
     * - Admin/supervisor: ven todos los leads.
     * - Vendedor: únicamente los asignados a su cuenta.
     * - Capturista: los que él capturó.
     */
    #[Computed]
    public function leads()
    {
        $user = Auth::user();

        $query = Lead::query()->with(['status', 'vendor', 'country', 'source']);

        if ($user->can('leads.view.all')) {
            // sin restricción adicional
        } elseif ($user->hasRole('vendedor')) {
            $query->forVendor($user->id);
        } else {
            $query->where('captured_by', $user->id);
        }

        return $query
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('company', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            })
            ->when($this->statusFilter, fn ($q) => $q->where('status_id', $this->statusFilter))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function statuses()
    {
        return Status::where('active', true)->orderBy('order')->get();
    }

    #[Computed]
    public function vendors()
    {
        return User::role('vendedor')->where('active', true)->orderBy('name')->get();
    }

    /**
     * Asigna o reasigna un lead a un vendedor (§4.1).
     * Reasignar NO borra actividades previas (§10). Genera notificación (§4.3).
     */
    public function assign(int $leadId, ?int $vendorId): void
    {
        abort_unless(Auth::user()->can('leads.assign'), 403);

        if (! $vendorId) {
            return;
        }

        $lead = Lead::findOrFail($leadId);
        $previousVendorId = $lead->vendor_id;

        $lead->vendor_id = $vendorId;
        $lead->assigned_at = now();

        // Al asignar por primera vez, avanza de "Nuevo" a "Asignado" (§7).
        $asignado = Status::where('order', 2)->first();
        if ($asignado && $lead->status?->order === 1) {
            $lead->status_id = $asignado->id;
            $lead->statusHistories()->create([
                'from_status_id' => Status::where('order', 1)->value('id'),
                'to_status_id' => $asignado->id,
                'user_id' => Auth::id(),
            ]);
        }

        $lead->save();

        $vendor = User::findOrFail($vendorId);
        $type = $previousVendorId ? 'reasignacion' : 'nuevo_lead';

        $lead->recordTimeline('assigned', 'Lead asignado', [
            'administrador' => Auth::user()->name,
            'vendedor' => $vendor->name,
            'reasignacion' => (bool) $previousVendorId,
        ]);

        // Campanita interna + aviso por correo (§4.3).
        app(LeadNotifier::class)->notify($lead, $vendor, $type);

        session()->flash('status', "Lead «{$lead->full_name}» asignado a {$vendor->name}.");
    }

    /**
     * Elimina un lead (y sus adjuntos físicos). Solo administrador (§LeadPolicy).
     */
    public function deleteLead(int $leadId): void
    {
        $lead = Lead::findOrFail($leadId);

        abort_unless(auth()->user()->can('delete', $lead), 403);

        foreach ($lead->attachments as $attachment) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($attachment->path);
        }

        $name = $lead->full_name;
        $lead->delete(); // las relaciones (actividades, timeline, etc.) caen en cascada

        session()->flash('status', "Lead «{$name}» eliminado.");
    }
}; ?>

@php
    $avatarPalette = ['bg-brand-600', 'bg-indigo-500', 'bg-emerald-500', 'bg-amber-500', 'bg-rose-500', 'bg-sky-500'];
    $statusBadge = fn ($s) => $s?->is_won ? 'badge-success' : ($s?->is_final ? 'badge-neutral' : 'badge-brand');
@endphp

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow">Cartera</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ __('Bandeja de leads') }}</h2>
            <p class="text-sm text-muted">{{ $this->leads->total() }} {{ Str::plural('lead', $this->leads->total()) }} en total</p>
        </div>
        @can('leads.capture')
            <a href="{{ route('leads.create') }}" wire:navigate class="btn-primary">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                {{ __('Capturar lead') }}
            </a>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            {{ session('status') }}
        </div>
    @endif

    {{-- Búsqueda y filtros (§4.1) --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row">
        <div class="relative w-full sm:max-w-md">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="{{ __('Buscar por nombre, empresa, correo o teléfono') }}"
                   class="input pl-9" />
        </div>
        <select wire:model.live="statusFilter" class="select sm:w-56">
            <option value="">{{ __('Todos los estatus') }}</option>
            @foreach ($this->statuses as $status)
                <option value="{{ $status->id }}">{{ $status->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="surface-muted">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <th class="px-4 py-3">{{ __('Prospecto') }}</th>
                        <th class="px-4 py-3">{{ __('Empresa') }}</th>
                        <th class="px-4 py-3">{{ __('País / Fuente') }}</th>
                        <th class="px-4 py-3">{{ __('Estatus') }}</th>
                        <th class="px-4 py-3">{{ __('Vendedor') }}</th>
                        @can('leads.assign')
                            <th class="px-4 py-3">{{ __('Asignar') }}</th>
                        @endcan
                        @role('administrador')
                            <th class="px-4 py-3 text-right">{{ __('Acciones') }}</th>
                        @endrole
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($this->leads as $i => $lead)
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-xs font-semibold text-white {{ $avatarPalette[$i % count($avatarPalette)] }}">
                                        {{ Str::of($lead->full_name)->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('') ?: '?' }}
                                    </span>
                                    <div class="min-w-0">
                                        <a href="{{ route('leads.show', $lead) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-400">
                                            {{ $lead->full_name }}
                                        </a>
                                        <div class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $lead->email ?: $lead->phone ?: '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $lead->company ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                {{ $lead->country?->name ?: '—' }}
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $lead->source?->name }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="{{ $statusBadge($lead->status) }}">{{ $lead->status?->name }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $lead->vendor?->name ?: '—' }}</td>
                            @can('leads.assign')
                                <td class="px-4 py-3">
                                    <select wire:change="assign({{ $lead->id }}, $event.target.value)" class="select text-xs">
                                        <option value="">{{ __('— Asignar —') }}</option>
                                        @foreach ($this->vendors as $vendor)
                                            <option value="{{ $vendor->id }}" @selected($lead->vendor_id === $vendor->id)>
                                                {{ $vendor->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                            @endcan
                            @can('delete', $lead)
                                <td class="px-4 py-3 text-right">
                                    <button wire:click="deleteLead({{ $lead->id }})"
                                            wire:confirm="¿Eliminar a «{{ $lead->full_name }}»? Esta acción no se puede deshacer: borra también su historial, actividades y adjuntos."
                                            class="btn-danger-ghost btn-sm" title="{{ __('Eliminar') }}">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m-7 0v12a1 1 0 001 1h6a1 1 0 001-1V7"/></svg>
                                    </button>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <svg class="mx-auto h-10 w-10 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2h7M16 3v4M8 3v4M4 11h16"/></svg>
                                <p class="mt-3 text-sm text-muted">{{ __('No hay leads que coincidan.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $this->leads->links() }}
    </div>
</div>
