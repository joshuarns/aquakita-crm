<?php

use App\Models\Activity;
use App\Models\Lead;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    /** Leads asignados al vendedor autenticado (§4.2). */
    private function myLeads()
    {
        return Lead::query()->forVendor(Auth::id());
    }

    /** Actividades de mis leads (§6). */
    private function myActivities()
    {
        return Activity::query()->whereHas('lead', fn ($q) => $q->where('vendor_id', Auth::id()));
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'sin_contactar' => (clone $this->myLeads())
                ->whereNull('first_contact_at')
                ->whereHas('status', fn ($q) => $q->where('is_final', false))
                ->count(),
            'en_seguimiento' => (clone $this->myLeads())
                ->whereHas('status', fn ($q) => $q->where('order', 4))
                ->count(),
            'vencidos' => (clone $this->myActivities())->overdue()->count(),
            'ventas' => (clone $this->myLeads())
                ->whereHas('status', fn ($q) => $q->where('is_won', true))
                ->count(),
        ];
    }

    /** Agenda del día: seguimientos programados para hoy sin completar (§4.2). */
    #[Computed]
    public function agenda()
    {
        return $this->myActivities()
            ->with('lead')
            ->where('completed', false)
            ->whereNotNull('follow_up_at')
            ->whereDate('follow_up_at', today())
            ->orderBy('follow_up_at')
            ->get();
    }

    /** Seguimientos vencidos: permanecen visibles hasta completarse o reprogramarse (§6). */
    #[Computed]
    public function overdue()
    {
        return $this->myActivities()
            ->with('lead')
            ->overdue()
            ->orderBy('follow_up_at')
            ->get();
    }

    /** Mis leads más recientes (§4.2 acceso a la ficha e historial). */
    #[Computed]
    public function recentLeads()
    {
        return $this->myLeads()->with('status')->latest()->limit(10)->get();
    }

    /**
     * Marca un seguimiento como atendido (§6). El vendedor solo opera sus leads.
     */
    public function complete(int $activityId): void
    {
        $activity = Activity::with('lead')->findOrFail($activityId);

        $this->authorize('manage', $activity->lead);

        $activity->update(['completed' => true, 'completed_at' => now()]);

        $activity->lead->recordTimeline('follow_up', 'Seguimiento completado', [
            'tipo' => Activity::TYPES[$activity->type] ?? $activity->type,
            'responsable' => Auth::user()->name,
        ]);

        session()->flash('panel_status', 'Seguimiento marcado como completado.');
    }
}; ?>

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <h2 class="text-xl font-semibold text-gray-800 mb-6">{{ __('Mi panel') }}</h2>

        @if (session('panel_status'))
            <div class="mb-4 rounded-md border border-green-300 bg-green-50 p-3 text-sm text-green-800">
                {{ session('panel_status') }}
            </div>
        @endif

        {{-- Indicadores (§4.2) --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white shadow-sm rounded-lg p-5">
                <p class="text-3xl font-semibold text-gray-900">{{ $this->stats['sin_contactar'] }}</p>
                <p class="text-sm text-gray-500 mt-1">{{ __('Sin contactar') }}</p>
            </div>
            <div class="bg-white shadow-sm rounded-lg p-5">
                <p class="text-3xl font-semibold text-gray-900">{{ $this->stats['en_seguimiento'] }}</p>
                <p class="text-sm text-gray-500 mt-1">{{ __('En seguimiento') }}</p>
            </div>
            <div class="bg-white shadow-sm rounded-lg p-5 {{ $this->stats['vencidos'] > 0 ? 'ring-1 ring-red-200' : '' }}">
                <p class="text-3xl font-semibold {{ $this->stats['vencidos'] > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $this->stats['vencidos'] }}</p>
                <p class="text-sm text-gray-500 mt-1">{{ __('Seguimientos vencidos') }}</p>
            </div>
            <div class="bg-white shadow-sm rounded-lg p-5">
                <p class="text-3xl font-semibold text-gray-900">{{ $this->stats['ventas'] }}</p>
                <p class="text-sm text-gray-500 mt-1">{{ __('Ventas cerradas') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Seguimientos vencidos (§6) --}}
            <div class="bg-white shadow-sm rounded-lg p-5">
                <h3 class="font-semibold text-gray-700 mb-4 text-sm flex items-center gap-2">
                    {{ __('Seguimientos vencidos') }}
                    @if ($this->overdue->isNotEmpty())
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">{{ $this->overdue->count() }}</span>
                    @endif
                </h3>
                <ul class="divide-y divide-gray-100">
                    @forelse ($this->overdue as $activity)
                        <li class="py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('leads.show', $activity->lead) }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                    {{ $activity->lead->full_name }}
                                </a>
                                <p class="text-xs text-gray-500 truncate">
                                    {{ \App\Models\Activity::TYPES[$activity->type] ?? $activity->type }} ·
                                    {{ __('venció') }} {{ $activity->follow_up_at->format('d/m/Y H:i') }}
                                </p>
                            </div>
                            <button wire:click="complete({{ $activity->id }})"
                                    class="shrink-0 text-xs px-3 py-1 rounded-md bg-gray-800 text-white hover:bg-gray-700">
                                {{ __('Completar') }}
                            </button>
                        </li>
                    @empty
                        <li class="py-3 text-sm text-gray-500">{{ __('Sin seguimientos vencidos.') }}</li>
                    @endforelse
                </ul>
            </div>

            {{-- Agenda del día (§4.2) --}}
            <div class="bg-white shadow-sm rounded-lg p-5">
                <h3 class="font-semibold text-gray-700 mb-4 text-sm">{{ __('Agenda de hoy') }}</h3>
                <ul class="divide-y divide-gray-100">
                    @forelse ($this->agenda as $activity)
                        <li class="py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('leads.show', $activity->lead) }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                    {{ $activity->lead->full_name }}
                                </a>
                                <p class="text-xs text-gray-500 truncate">
                                    {{ \App\Models\Activity::TYPES[$activity->type] ?? $activity->type }} ·
                                    {{ $activity->follow_up_at->format('H:i') }}
                                </p>
                            </div>
                            <button wire:click="complete({{ $activity->id }})"
                                    class="shrink-0 text-xs px-3 py-1 rounded-md bg-gray-800 text-white hover:bg-gray-700">
                                {{ __('Completar') }}
                            </button>
                        </li>
                    @empty
                        <li class="py-3 text-sm text-gray-500">{{ __('Nada agendado para hoy.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- Mis leads (§4.2 acceso a la ficha) --}}
        <div class="bg-white shadow-sm rounded-lg p-5 mt-6">
            <h3 class="font-semibold text-gray-700 mb-4 text-sm">{{ __('Mis leads') }}</h3>
            <ul class="divide-y divide-gray-100">
                @forelse ($this->recentLeads as $lead)
                    <li class="py-3 flex items-center justify-between">
                        <a href="{{ route('leads.show', $lead) }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                            {{ $lead->full_name }}
                            <span class="text-gray-500 font-normal">· {{ $lead->company ?: '—' }}</span>
                        </a>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700">
                            {{ $lead->status?->name }}
                        </span>
                    </li>
                @empty
                    <li class="py-3 text-sm text-gray-500">{{ __('Aún no tienes leads asignados.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
