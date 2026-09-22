<?php

use App\Models\Lead;
use App\Models\Source;
use App\Models\Status;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    private function canViewAll(): bool
    {
        return auth()->user()->can('leads.view.all');
    }

    private function scopedLeads(): Builder
    {
        return Lead::query()
            ->when(! $this->canViewAll(), fn ($q) => $q->where('vendor_id', auth()->id()));
    }

    /**
     * Calcula la variación porcentual entre dos periodos.
     *
     * @return array{dir: string, text: string}
     */
    private function delta(int $current, int $previous): array
    {
        if ($previous === 0) {
            return $current > 0
                ? ['dir' => 'up', 'text' => 'Nuevo']
                : ['dir' => 'flat', 'text' => '0%'];
        }

        $change = round(($current - $previous) / $previous * 100);

        return [
            'dir' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
            'text' => ($change > 0 ? '+' : '').$change.'%',
        ];
    }

    /**
     * Tarjetas de indicadores con icono y tendencia.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cards(): array
    {
        $total = (clone $this->scopedLeads())->count();

        $nuevosMes = (clone $this->scopedLeads())->where('created_at', '>=', now()->startOfMonth())->count();
        $nuevosPrev = (clone $this->scopedLeads())
            ->whereBetween('created_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])
            ->count();

        $ventas = (clone $this->scopedLeads())->whereHas('status', fn ($q) => $q->where('is_won', true))->count();
        $ventasMes = (clone $this->scopedLeads())
            ->where('updated_at', '>=', now()->startOfMonth())
            ->whereHas('status', fn ($q) => $q->where('is_won', true))->count();
        $ventasPrev = (clone $this->scopedLeads())
            ->whereBetween('updated_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])
            ->whereHas('status', fn ($q) => $q->where('is_won', true))->count();

        $conversion = $total > 0 ? round($ventas / $total * 100) : 0;

        $cards = [
            [
                'label' => 'Leads totales', 'value' => number_format($total), 'icon' => 'users', 'tone' => 'teal',
                'trend' => ['dir' => 'up', 'text' => "+{$nuevosMes}"], 'sub' => 'este mes',
            ],
            [
                'label' => 'Nuevos del mes', 'value' => number_format($nuevosMes), 'icon' => 'spark', 'tone' => 'indigo',
                'trend' => $this->delta($nuevosMes, $nuevosPrev), 'sub' => 'vs. mes anterior',
            ],
            [
                'label' => 'Ventas cerradas', 'value' => number_format($ventas), 'icon' => 'trophy', 'tone' => 'emerald',
                'trend' => $this->delta($ventasMes, $ventasPrev), 'sub' => "{$conversion}% conversión",
            ],
        ];

        if ($this->canViewAll()) {
            $sinAsignar = (clone $this->scopedLeads())->whereNull('vendor_id')->count();
            $cards[] = [
                'label' => 'Sin asignar', 'value' => number_format($sinAsignar), 'icon' => 'inbox', 'tone' => 'amber',
                'trend' => null, 'sub' => 'esperando vendedor',
            ];
        } else {
            $pendientes = (clone $this->scopedLeads())
                ->whereNotNull('next_follow_up_at')
                ->whereDate('next_follow_up_at', '<=', today())
                ->count();
            $cards[] = [
                'label' => 'Por atender', 'value' => number_format($pendientes), 'icon' => 'clock', 'tone' => 'amber',
                'trend' => null, 'sub' => 'seguimientos vencidos',
            ];
        }

        return $cards;
    }

    /**
     * Serie diaria de leads capturados (últimos 14 días) para el gráfico.
     *
     * @return array<int, array{label: string, value: int}>
     */
    public function activitySeries(): array
    {
        $from = today()->subDays(13);

        $raw = (clone $this->scopedLeads())
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as d, count(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $series = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = today()->subDays($i);
            $series[] = [
                'label' => $day->format('d/m'),
                'value' => (int) ($raw[$day->toDateString()] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Fuentes de llegada principales.
     *
     * @return Collection<int, array{name: string, count: int}>
     */
    public function topSources(): Collection
    {
        $counts = (clone $this->scopedLeads())
            ->selectRaw('source_id, count(*) as total')
            ->groupBy('source_id')
            ->pluck('total', 'source_id');

        $names = Source::whereIn('id', $counts->keys()->filter())->pluck('name', 'id');

        return collect($counts)
            ->map(fn ($count, $id) => [
                'name' => $names[$id] ?? 'Sin origen',
                'count' => (int) $count,
            ])
            ->sortByDesc('count')
            ->take(5)
            ->values();
    }

    /**
     * Distribución por estatus.
     *
     * @return Collection<int, array{name: string, count: int, is_won: bool, is_final: bool}>
     */
    public function byStatus(): Collection
    {
        $counts = (clone $this->scopedLeads())
            ->selectRaw('status_id, count(*) as total')
            ->groupBy('status_id')
            ->pluck('total', 'status_id');

        return Status::orderBy('order')->get()
            ->map(fn ($s) => [
                'name' => $s->name,
                'count' => (int) ($counts[$s->id] ?? 0),
                'is_won' => (bool) $s->is_won,
                'is_final' => (bool) $s->is_final,
            ])
            ->filter(fn ($row) => $row['count'] > 0)
            ->values();
    }

    /**
     * @return Collection<int, Lead>
     */
    public function recentLeads(): Collection
    {
        return (clone $this->scopedLeads())
            ->with(['status', 'source'])
            ->latest()
            ->limit(6)
            ->get();
    }
}; ?>

@php
    $tones = [
        'teal' => 'bg-[#0e7490]/10 text-[#0e7490]',
        'indigo' => 'bg-indigo-50 text-indigo-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'amber' => 'bg-amber-50 text-amber-600',
    ];
    $icons = [
        'users' => 'M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m6-1.13a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm6 0a4 4 0 1 0-3-6.65',
        'spark' => 'M13 2 3 14h7l-1 8 10-12h-7l1-8Z',
        'trophy' => 'M8 21h8m-4-4v4m7-17H5v4a5 5 0 0 0 5 5h4a5 5 0 0 0 5-5V4Zm0 0h3v2a3 3 0 0 1-3 3m-15-5H2v2a3 3 0 0 0 3 3',
        'inbox' => 'M22 12h-6l-2 3h-4l-2-3H2m20 0V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v6m20 0v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6',
        'clock' => 'M12 6v6l4 2m6-2a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z',
    ];
    $avatarPalette = ['bg-[#0e7490]', 'bg-indigo-500', 'bg-emerald-500', 'bg-amber-500', 'bg-rose-500', 'bg-sky-500'];
@endphp

<div class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Encabezado --}}
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#0891b2]">
                    {{ now()->translatedFormat('l, d \d\e F Y') }}
                </p>
                <h2 class="mt-1 text-2xl font-bold text-gray-900">Hola, {{ auth()->user()->name }}</h2>
                <p class="text-sm text-gray-500">Este es el resumen de tu operación comercial.</p>
            </div>
            @can('leads.capture')
                <a href="{{ route('leads.create') }}" wire:navigate
                   class="inline-flex items-center gap-2 rounded-lg bg-[#0e7490] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#155e75]">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Capturar lead
                </a>
            @endcan
        </div>

        {{-- Tarjetas KPI --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($this->cards() as $card)
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition hover:shadow-md">
                    <div class="flex items-start justify-between">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $tones[$card['tone']] }}">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="{{ $icons[$card['icon']] }}"/>
                            </svg>
                        </span>
                        @if ($card['trend'])
                            @php($dir = $card['trend']['dir'])
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold
                                {{ $dir === 'up' ? 'bg-emerald-50 text-emerald-600' : ($dir === 'down' ? 'bg-rose-50 text-rose-600' : 'bg-gray-100 text-gray-500') }}">
                                @if ($dir === 'up')
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m6 15 6-6 6 6"/></svg>
                                @elseif ($dir === 'down')
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m6 9 6 6 6-6"/></svg>
                                @endif
                                {{ $card['trend']['text'] }}
                            </span>
                        @endif
                    </div>
                    <p class="mt-4 text-3xl font-bold tracking-tight text-gray-900">{{ $card['value'] }}</p>
                    <p class="mt-1 text-sm font-medium text-gray-600">{{ $card['label'] }}</p>
                    <p class="text-xs text-gray-400">{{ $card['sub'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Gráfico de actividad + Fuentes --}}
        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-800">Actividad de captación</h3>
                        <p class="text-xs text-gray-400">Leads capturados · últimos 14 días</p>
                    </div>
                </div>

                @php($series = $this->activitySeries())
                @php($values = array_column($series, 'value'))
                @php($maxV = max($values) ?: 1)
                @php($n = count($series))
                @php($w = 640)
                @php($h = 160)
                @php($step = $n > 1 ? $w / ($n - 1) : $w)
                @php($pts = collect($series)->map(fn ($p, $i) => round($i * $step, 1).','.round($h - ($p['value'] / $maxV) * ($h - 20) - 6, 1)))
                @php($line = $pts->implode(' '))
                @php($area = '0,'.$h.' '.$line.' '.round(($n - 1) * $step, 1).','.$h)

                <div class="mt-4 overflow-hidden">
                    <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" class="h-40 w-full">
                        <defs>
                            <linearGradient id="areaFill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#0e7490" stop-opacity="0.22"/>
                                <stop offset="100%" stop-color="#0e7490" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        <polygon points="{{ $area }}" fill="url(#areaFill)"/>
                        <polyline points="{{ $line }}" fill="none" stroke="#0e7490" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
                        @foreach ($series as $i => $p)
                            @if ($p['value'] > 0)
                                <circle cx="{{ round($i * $step, 1) }}" cy="{{ round($h - ($p['value'] / $maxV) * ($h - 20) - 6, 1) }}" r="3" fill="#0e7490"/>
                            @endif
                        @endforeach
                    </svg>
                    <div class="mt-2 flex justify-between text-[10px] text-gray-400">
                        <span>{{ $series[0]['label'] }}</span>
                        <span>{{ $series[intdiv($n, 2)]['label'] }}</span>
                        <span>{{ $series[$n - 1]['label'] }}</span>
                    </div>
                </div>
            </div>

            {{-- Fuentes principales --}}
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h3 class="font-semibold text-gray-800">Fuentes principales</h3>
                <p class="text-xs text-gray-400">De dónde llegan tus leads</p>
                @php($sources = $this->topSources())
                @php($srcMax = $sources->max('count') ?: 1)
                <div class="mt-4 space-y-4">
                    @forelse ($sources as $src)
                        <div>
                            <div class="mb-1 flex items-center justify-between text-sm">
                                <span class="text-gray-600">{{ $src['name'] }}</span>
                                <span class="font-semibold text-gray-800">{{ $src['count'] }}</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full bg-[#0891b2]" style="width: {{ max(6, round($src['count'] / $srcMax * 100)) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="py-8 text-center text-sm text-gray-400">Sin datos de origen.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Estatus + Recientes --}}
        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-5">
            <div class="lg:col-span-3 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h3 class="font-semibold text-gray-800">Embudo por estatus</h3>
                @php($rows = $this->byStatus())
                @php($totalRows = $rows->sum('count') ?: 1)
                @php($maxRow = $rows->max('count') ?: 1)
                <div class="mt-4 space-y-3">
                    @forelse ($rows as $row)
                        <div>
                            <div class="mb-1 flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2 text-gray-600">
                                    <span class="h-2 w-2 rounded-full {{ $row['is_won'] ? 'bg-emerald-500' : ($row['is_final'] ? 'bg-gray-400' : 'bg-[#0e7490]') }}"></span>
                                    {{ $row['name'] }}
                                </span>
                                <span class="text-gray-500">
                                    <span class="font-semibold text-gray-800">{{ $row['count'] }}</span>
                                    <span class="text-xs text-gray-400">· {{ round($row['count'] / $totalRows * 100) }}%</span>
                                </span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full {{ $row['is_won'] ? 'bg-emerald-500' : ($row['is_final'] ? 'bg-gray-400' : 'bg-[#0e7490]') }}"
                                     style="width: {{ max(4, round($row['count'] / $maxRow * 100)) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="py-8 text-center text-sm text-gray-400">Aún no hay leads para mostrar.</p>
                    @endforelse
                </div>
            </div>

            <div class="lg:col-span-2 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">Actividad reciente</h3>
                    <a href="{{ route('leads.index') }}" wire:navigate class="text-sm text-[#0891b2] hover:underline">Ver todos</a>
                </div>
                <div class="mt-2 divide-y divide-gray-100">
                    @forelse ($this->recentLeads() as $i => $lead)
                        <a href="{{ route('leads.show', $lead) }}" wire:navigate
                           class="flex items-center gap-3 py-3 transition hover:bg-gray-50">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white {{ $avatarPalette[$i % count($avatarPalette)] }}">
                                {{ Str::of($lead->full_name)->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('') ?: '?' }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900">{{ $lead->full_name }}</p>
                                <p class="truncate text-xs text-gray-400">{{ $lead->source?->name ?? 'Sin origen' }} · {{ $lead->created_at->diffForHumans() }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-[#0891b2]/10 px-2.5 py-1 text-xs font-medium text-[#0e7490]">{{ $lead->status->name }}</span>
                        </a>
                    @empty
                        <p class="py-8 text-center text-sm text-gray-400">Sin leads todavía.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
