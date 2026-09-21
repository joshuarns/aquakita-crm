<?php

use App\Models\Activity;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    /** Leads del periodo seleccionado (§8 "por periodo"). */
    private function baseQuery(): Builder
    {
        return Lead::query()
            ->when($this->from !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->to));
    }

    /**
     * Indicadores prioritarios para Aquakita (§8).
     *
     * @return array<string, int|float|null>
     */
    #[Computed]
    public function stats(): array
    {
        $recibidos = (clone $this->baseQuery())->count();
        $atendidos = (clone $this->baseQuery())->whereNotNull('first_contact_at')->count();
        $ventas = (clone $this->baseQuery())->whereHas('status', fn ($q) => $q->where('is_won', true))->count();

        // Leads cotizados: alguna vez llegaron a "Cotización enviada" (§7 orden 7).
        $cotizadoStatusId = Status::where('order', 7)->value('id');
        $cotizados = (clone $this->baseQuery())
            ->whereHas('statusHistories', fn ($q) => $q->where('to_status_id', $cotizadoStatusId))
            ->count();

        return [
            'recibidos' => $recibidos,
            'sin_asignar' => (clone $this->baseQuery())->whereNull('vendor_id')->count(),
            'sin_abrir' => (clone $this->baseQuery())->whereNotNull('vendor_id')->whereNull('first_opened_at')->count(),
            'abiertos_sin_contacto' => (clone $this->baseQuery())->whereNotNull('first_opened_at')->whereNull('first_contact_at')->count(),
            'atendidos' => $atendidos,
            'ventas' => $ventas,
            'cotizados' => $cotizados,
            'vencidos' => Activity::query()->overdue()->count(),
            'tiempo_apertura' => $this->avgMinutes('first_opened_at'),
            'tiempo_primer_contacto' => $this->avgMinutes('first_contact_at'),
            'conv_cotizacion' => $atendidos > 0 ? round($cotizados / $atendidos * 100, 1) : 0.0,
            'conv_venta' => $recibidos > 0 ? round($ventas / $recibidos * 100, 1) : 0.0,
        ];
    }

    /**
     * Promedio en minutos entre la asignación y el evento dado (§8 tiempos).
     */
    private function avgMinutes(string $column): ?float
    {
        $leads = (clone $this->baseQuery())
            ->whereNotNull('assigned_at')
            ->whereNotNull($column)
            ->get(['assigned_at', $column]);

        if ($leads->isEmpty()) {
            return null;
        }

        $total = $leads->sum(fn ($lead) => $lead->assigned_at->diffInMinutes($lead->{$column}));

        return round($total / $leads->count(), 1);
    }

    /**
     * Conteo por columna de catálogo (§8 volumen y origen).
     *
     * @return \Illuminate\Support\Collection<int, object{name: string, total: int}>
     */
    private function countBy(string $column, string $model): \Illuminate\Support\Collection
    {
        $counts = (clone $this->baseQuery())
            ->whereNotNull($column)
            ->selectRaw("$column as grp, count(*) as total")
            ->groupBy($column)
            ->pluck('total', 'grp');

        return $model::whereIn('id', $counts->keys())
            ->get()
            ->map(fn ($item) => (object) ['name' => $item->name, 'total' => (int) $counts[$item->id]])
            ->sortByDesc('total')
            ->values();
    }

    #[Computed]
    public function bySource()
    {
        return $this->countBy('source_id', \App\Models\Source::class);
    }

    #[Computed]
    public function byStatus()
    {
        $counts = (clone $this->baseQuery())
            ->selectRaw('status_id as grp, count(*) as total')
            ->groupBy('status_id')
            ->pluck('total', 'grp');

        return Status::orderBy('order')->get()
            ->map(fn ($s) => (object) ['name' => $s->name, 'total' => (int) ($counts[$s->id] ?? 0)]);
    }

    /**
     * Desempeño por vendedor (§8): asignados, ventas y conversión.
     */
    #[Computed]
    public function byVendor()
    {
        return User::role('vendedor')->orderBy('name')->get()->map(function ($vendor) {
            $asignados = (clone $this->baseQuery())->where('vendor_id', $vendor->id)->count();
            $ventas = (clone $this->baseQuery())
                ->where('vendor_id', $vendor->id)
                ->whereHas('status', fn ($q) => $q->where('is_won', true))
                ->count();

            return (object) [
                'name' => $vendor->name,
                'asignados' => $asignados,
                'ventas' => $ventas,
                'conversion' => $asignados > 0 ? round($ventas / $asignados * 100, 1) : 0.0,
            ];
        });
    }

    private function humanMinutes(?float $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }
        if ($minutes < 60) {
            return round($minutes).' min';
        }

        return round($minutes / 60, 1).' h';
    }

    public function with(): array
    {
        return ['humanMinutes' => fn ($m) => $this->humanMinutes($m)];
    }
}; ?>

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
            <h2 class="text-xl font-semibold text-gray-800">{{ __('Panel administrativo') }}</h2>
            <div class="flex items-end gap-2">
                <div>
                    <label class="block text-xs text-gray-500">{{ __('Desde') }}</label>
                    <x-text-input wire:model.live="from" type="date" class="text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-gray-500">{{ __('Hasta') }}</label>
                    <x-text-input wire:model.live="to" type="date" class="text-sm" />
                </div>
            </div>
        </div>

        {{-- Indicadores prioritarios (§8) --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            @php
                $cards = [
                    ['Leads recibidos', $this->stats['recibidos'], null],
                    ['Sin asignar', $this->stats['sin_asignar'], $this->stats['sin_asignar'] > 0 ? 'amber' : null],
                    ['Asignados sin abrir', $this->stats['sin_abrir'], $this->stats['sin_abrir'] > 0 ? 'amber' : null],
                    ['Abiertos sin contacto', $this->stats['abiertos_sin_contacto'], null],
                    ['Ventas cerradas', $this->stats['ventas'], 'green'],
                    ['Cotizados', $this->stats['cotizados'], null],
                    ['Seguimientos vencidos', $this->stats['vencidos'], $this->stats['vencidos'] > 0 ? 'red' : null],
                    ['Leads atendidos', $this->stats['atendidos'], null],
                ];
                $tone = ['amber' => 'text-amber-600', 'red' => 'text-red-600', 'green' => 'text-green-600'];
            @endphp
            @foreach ($cards as [$label, $value, $color])
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-3xl font-semibold {{ $color ? $tone[$color] : 'text-gray-900' }}">{{ $value }}</p>
                    <p class="text-sm text-gray-500 mt-1">{{ __($label) }}</p>
                </div>
            @endforeach
        </div>

        {{-- Tiempos y conversiones (§8 indicadores prioritarios) --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-indigo-50 rounded-lg p-5">
                <p class="text-2xl font-semibold text-indigo-700">{{ $humanMinutes($this->stats['tiempo_apertura']) }}</p>
                <p class="text-sm text-indigo-900/70 mt-1">{{ __('Tiempo prom. de apertura') }}</p>
            </div>
            <div class="bg-indigo-50 rounded-lg p-5">
                <p class="text-2xl font-semibold text-indigo-700">{{ $humanMinutes($this->stats['tiempo_primer_contacto']) }}</p>
                <p class="text-sm text-indigo-900/70 mt-1">{{ __('Tiempo prom. primer contacto') }}</p>
            </div>
            <div class="bg-indigo-50 rounded-lg p-5">
                <p class="text-2xl font-semibold text-indigo-700">{{ $this->stats['conv_cotizacion'] }}%</p>
                <p class="text-sm text-indigo-900/70 mt-1">{{ __('Conversión a cotización') }}</p>
            </div>
            <div class="bg-indigo-50 rounded-lg p-5">
                <p class="text-2xl font-semibold text-indigo-700">{{ $this->stats['conv_venta'] }}%</p>
                <p class="text-sm text-indigo-900/70 mt-1">{{ __('Conversión a venta') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Volumen por fuente (§8) --}}
            <div class="bg-white shadow-sm rounded-lg p-5">
                <h3 class="font-semibold text-gray-700 mb-4 text-sm">{{ __('Volumen por fuente') }}</h3>
                <ul class="space-y-2 text-sm">
                    @forelse ($this->bySource as $row)
                        <li class="flex items-center justify-between">
                            <span class="text-gray-700">{{ $row->name }}</span>
                            <span class="font-medium text-gray-900">{{ $row->total }}</span>
                        </li>
                    @empty
                        <li class="text-gray-500">{{ __('Sin datos en el periodo.') }}</li>
                    @endforelse
                </ul>
            </div>

            {{-- Distribución por estatus (§8 resultados) --}}
            <div class="bg-white shadow-sm rounded-lg p-5">
                <h3 class="font-semibold text-gray-700 mb-4 text-sm">{{ __('Leads por estatus') }}</h3>
                <ul class="space-y-2 text-sm">
                    @foreach ($this->byStatus as $row)
                        <li class="flex items-center justify-between">
                            <span class="text-gray-700">{{ $row->name }}</span>
                            <span class="font-medium text-gray-900">{{ $row->total }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Desempeño por vendedor (§8) --}}
        <div class="bg-white shadow-sm rounded-lg p-5 mt-6">
            <h3 class="font-semibold text-gray-700 mb-4 text-sm">{{ __('Desempeño por vendedor') }}</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-100">
                            <th class="py-2 pr-4">{{ __('Vendedor') }}</th>
                            <th class="py-2 pr-4">{{ __('Asignados') }}</th>
                            <th class="py-2 pr-4">{{ __('Ventas') }}</th>
                            <th class="py-2">{{ __('Conversión') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($this->byVendor as $row)
                            <tr>
                                <td class="py-2 pr-4 text-gray-900">{{ $row->name }}</td>
                                <td class="py-2 pr-4 text-gray-700">{{ $row->asignados }}</td>
                                <td class="py-2 pr-4 text-gray-700">{{ $row->ventas }}</td>
                                <td class="py-2 text-gray-700">{{ $row->conversion }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-3 text-gray-500">{{ __('No hay vendedores.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
