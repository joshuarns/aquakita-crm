<?php

use App\Models\Lead;
use App\Models\LeadNotification;
use App\Models\Status;
use App\Models\User;
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

        $vendor = User::find($vendorId);
        $type = $previousVendorId ? 'reasignacion' : 'nuevo_lead';

        $lead->recordTimeline('assigned', 'Lead asignado', [
            'administrador' => Auth::user()->name,
            'vendedor' => $vendor?->name,
            'reasignacion' => (bool) $previousVendorId,
        ]);

        LeadNotification::create([
            'lead_id' => $lead->id,
            'vendor_id' => $vendorId,
            'type' => $type,
            'message' => $type === 'reasignacion'
                ? "El lead {$lead->full_name} fue reasignado a tu cuenta."
                : "Se te asignó el lead {$lead->full_name}.",
            'sent_at' => now(),
        ]);

        session()->flash('status', "Lead «{$lead->full_name}» asignado a {$vendor?->name}.");
    }
}; ?>

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">{{ __('Bandeja de leads') }}</h2>
            @can('leads.capture')
                <a href="{{ route('leads.create') }}" wire:navigate
                   class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-gray-700">
                    {{ __('Capturar lead') }}
                </a>
            @endcan
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-md border border-green-300 bg-green-50 p-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        {{-- Búsqueda y filtros (§4.1) --}}
        <div class="mb-4 flex flex-col sm:flex-row gap-3">
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="{{ __('Buscar por nombre, empresa, correo o teléfono') }}"
                   class="w-full sm:max-w-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
            <select wire:model.live="statusFilter"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                <option value="">{{ __('Todos los estatus') }}</option>
                @foreach ($this->statuses as $status)
                    <option value="{{ $status->id }}">{{ $status->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <th class="px-4 py-3">{{ __('Prospecto') }}</th>
                        <th class="px-4 py-3">{{ __('Empresa') }}</th>
                        <th class="px-4 py-3">{{ __('País / Fuente') }}</th>
                        <th class="px-4 py-3">{{ __('Estatus') }}</th>
                        <th class="px-4 py-3">{{ __('Vendedor') }}</th>
                        @can('leads.assign')
                            <th class="px-4 py-3">{{ __('Asignar') }}</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->leads as $lead)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('leads.show', $lead) }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-800">
                                    {{ $lead->full_name }}
                                </a>
                                <div class="text-xs text-gray-500">{{ $lead->email ?: $lead->phone }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $lead->company ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $lead->country?->name ?: '—' }}
                                <div class="text-xs text-gray-500">{{ $lead->source?->name }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700">
                                    {{ $lead->status?->name }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $lead->vendor?->name ?: '—' }}</td>
                            @can('leads.assign')
                                <td class="px-4 py-3">
                                    <select wire:change="assign({{ $lead->id }}, $event.target.value)"
                                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-xs">
                                        <option value="">{{ __('— Asignar —') }}</option>
                                        @foreach ($this->vendors as $vendor)
                                            <option value="{{ $vendor->id }}" @selected($lead->vendor_id === $vendor->id)>
                                                {{ $vendor->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                {{ __('No hay leads que coincidan.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->leads->links() }}
        </div>
    </div>
</div>
