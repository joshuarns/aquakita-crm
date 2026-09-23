<?php

use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $vendorId = null;

    public function mount(): void
    {
        $this->vendorId ??= User::role('vendedor')->where('active', true)->orderBy('name')->value('id');
    }

    /**
     * Vendedores con su resumen de carga (para las tarjetas seleccionables).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function vendors(): Collection
    {
        return User::role('vendedor')->where('active', true)->orderBy('name')->get()
            ->map(function (User $v) {
                $pending = Activity::query()
                    ->whereHas('lead', fn ($q) => $q->where('vendor_id', $v->id))
                    ->where('completed', false);

                return [
                    'id' => $v->id,
                    'name' => $v->name,
                    'asignados' => Lead::query()->where('vendor_id', $v->id)
                        ->whereHas('status', fn ($q) => $q->where('is_final', false))->count(),
                    'pendientes' => (clone $pending)->whereNotNull('follow_up_at')
                        ->whereDate('follow_up_at', '<=', today())->count(),
                    'vencidos' => (clone $pending)->overdue()->count(),
                ];
            });
    }

    public function selectedVendor(): ?User
    {
        return $this->vendorId ? User::find($this->vendorId) : null;
    }

    private function vendorLeads()
    {
        return Lead::query()->where('vendor_id', $this->vendorId);
    }

    private function vendorActivities()
    {
        return Activity::query()->whereHas('lead', fn ($q) => $q->where('vendor_id', $this->vendorId));
    }

    /**
     * Indicadores del vendedor seleccionado.
     *
     * @return array<string, int>
     */
    public function stats(): array
    {
        return [
            'asignados' => (clone $this->vendorLeads())->whereHas('status', fn ($q) => $q->where('is_final', false))->count(),
            'sin_contactar' => (clone $this->vendorLeads())->whereNull('first_contact_at')
                ->whereHas('status', fn ($q) => $q->where('is_final', false))->count(),
            'pendientes' => (clone $this->vendorActivities())->where('completed', false)
                ->whereNotNull('follow_up_at')->whereDate('follow_up_at', '<=', today())->count(),
            'ventas' => (clone $this->vendorLeads())->whereHas('status', fn ($q) => $q->where('is_won', true))->count(),
        ];
    }

    /**
     * Leads asignados al vendedor.
     */
    public function assignedLeads(): Collection
    {
        return (clone $this->vendorLeads())->with('status', 'source')->latest()->limit(12)->get();
    }

    /**
     * Actividades pendientes (hoy y vencidas).
     */
    public function pendingActivities(): Collection
    {
        return (clone $this->vendorActivities())
            ->with('lead')
            ->where('completed', false)
            ->whereNotNull('follow_up_at')
            ->whereDate('follow_up_at', '<=', today())
            ->orderBy('follow_up_at')
            ->limit(15)
            ->get();
    }

    /**
     * Actividad reciente: interacciones y respuestas de los clientes.
     */
    public function recentActivities(): Collection
    {
        return (clone $this->vendorActivities())
            ->with('lead')
            ->latest()
            ->limit(10)
            ->get();
    }

    public function typeLabels(): array
    {
        return Activity::TYPES;
    }
}; ?>

<div class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-6">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#0891b2]">Supervisión</p>
            <h2 class="mt-1 text-2xl font-bold text-gray-900">Panel de equipo</h2>
            <p class="text-sm text-gray-500">Carga, pendientes y respuestas de clientes por vendedor.</p>
        </div>

        {{-- Selector de vendedores --}}
        @php($vendors = $this->vendors())
        @if ($vendors->isEmpty())
            <div class="rounded-2xl border border-gray-100 bg-white p-8 text-center text-gray-500 shadow-sm">
                No hay vendedores activos todavía.
            </div>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($vendors as $v)
                    <button wire:click="$set('vendorId', {{ $v['id'] }})"
                            class="rounded-2xl border p-4 text-left transition {{ $vendorId === $v['id'] ? 'border-[#0e7490] bg-[#0e7490]/5 ring-1 ring-[#0e7490]' : 'border-gray-100 bg-white hover:border-gray-200' }} shadow-sm">
                        <div class="flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#0e7490] text-xs font-semibold text-white">
                                {{ Str::of($v['name'])->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('') }}
                            </span>
                            <span class="truncate text-sm font-semibold text-gray-800">{{ $v['name'] }}</span>
                        </div>
                        <div class="mt-3 flex items-center gap-3 text-xs">
                            <span class="text-gray-500">{{ $v['asignados'] }} activos</span>
                            @if ($v['vencidos'] > 0)
                                <span class="rounded-full bg-rose-50 px-2 py-0.5 font-semibold text-rose-600">{{ $v['vencidos'] }} vencidos</span>
                            @elseif ($v['pendientes'] > 0)
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 font-semibold text-amber-600">{{ $v['pendientes'] }} hoy</span>
                            @else
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 font-medium text-emerald-600">al día</span>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>

            @php($vendor = $this->selectedVendor())
            @if ($vendor)
                {{-- KPIs del vendedor --}}
                @php($s = $this->stats())
                <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    @foreach ([
                        ['Leads activos', $s['asignados'], 'asignados'],
                        ['Sin contactar', $s['sin_contactar'], 'aún sin primer contacto'],
                        ['Pendientes hoy/vencidos', $s['pendientes'], 'seguimientos por hacer'],
                        ['Ventas cerradas', $s['ventas'], 'ganados'],
                    ] as [$label, $value, $hint])
                        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                            <p class="text-3xl font-bold text-gray-900">{{ $value }}</p>
                            <p class="mt-1 text-sm font-medium text-gray-600">{{ $label }}</p>
                            <p class="text-xs text-gray-400">{{ $hint }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {{-- Leads asignados --}}
                    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-gray-800">Leads asignados</h3>
                        <p class="text-xs text-gray-400">Lo que se le asignó</p>
                        <div class="mt-3 divide-y divide-gray-100">
                            @forelse ($this->assignedLeads() as $lead)
                                <a href="{{ route('leads.show', $lead) }}" wire:navigate class="flex items-center justify-between gap-2 py-2.5 hover:bg-gray-50">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900">{{ $lead->full_name }}</p>
                                        <p class="truncate text-xs text-gray-400">{{ $lead->source?->name ?? 'Sin origen' }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-[#0891b2]/10 px-2 py-0.5 text-xs font-medium text-[#0e7490]">{{ $lead->status->name }}</span>
                                </a>
                            @empty
                                <p class="py-6 text-center text-sm text-gray-400">Sin leads asignados.</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Pendientes --}}
                    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-gray-800">Actividades pendientes</h3>
                        <p class="text-xs text-gray-400">Por hacer (hoy y vencidas)</p>
                        <div class="mt-3 divide-y divide-gray-100">
                            @forelse ($this->pendingActivities() as $act)
                                @php($vencida = $act->follow_up_at->isPast() && ! $act->follow_up_at->isToday())
                                <a href="{{ route('leads.show', $act->lead) }}" wire:navigate class="block py-2.5 hover:bg-gray-50">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="truncate text-sm font-medium text-gray-900">{{ $act->lead->full_name }}</p>
                                        <span class="shrink-0 text-xs font-medium {{ $vencida ? 'text-rose-600' : 'text-amber-600' }}">
                                            {{ $act->follow_up_at->isToday() ? 'Hoy' : $act->follow_up_at->translatedFormat('d/m') }}
                                        </span>
                                    </div>
                                    <p class="truncate text-xs text-gray-500">
                                        {{ $this->typeLabels()[$act->type] ?? $act->type }}{{ $act->next_action ? ' · '.$act->next_action : '' }}
                                    </p>
                                </a>
                            @empty
                                <p class="py-6 text-center text-sm text-gray-400">Sin pendientes. 🎉</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Actividad reciente / respuestas --}}
                    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-gray-800">Respuestas y actividad</h3>
                        <p class="text-xs text-gray-400">Últimas interacciones con clientes</p>
                        <div class="mt-3 space-y-3">
                            @forelse ($this->recentActivities() as $act)
                                <div class="border-b border-gray-100 pb-3 last:border-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $this->typeLabels()[$act->type] ?? $act->type }}</span>
                                        <span class="text-xs text-gray-400">{{ $act->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="mt-1 truncate text-sm font-medium text-gray-800">{{ $act->lead->full_name }}</p>
                                    @if ($act->result)
                                        <p class="text-xs text-gray-500"><span class="font-medium text-gray-600">Respuesta:</span> {{ $act->result }}</p>
                                    @endif
                                    @if ($act->comment)
                                        <p class="line-clamp-2 text-xs text-gray-500">{{ $act->comment }}</p>
                                    @endif
                                </div>
                            @empty
                                <p class="py-6 text-center text-sm text-gray-400">Sin actividad registrada.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
