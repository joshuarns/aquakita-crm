<?php

use App\Models\Lead;
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
    public string $channel = ''; // '' | email | whatsapp

    public function updatingChannel(): void
    {
        $this->resetPage();
    }

    /**
     * Acota a los leads que el usuario puede ver (misma regla que la bandeja).
     */
    private function scope($query)
    {
        $user = Auth::user();

        if ($user->can('leads.view.all')) {
            return $query;
        }

        if ($user->hasRole('vendedor')) {
            return $query->where('vendor_id', $user->id);
        }

        return $query->where('captured_by', $user->id);
    }

    #[Computed]
    public function conversations()
    {
        $channel = $this->channel;

        $constraint = fn ($q) => $channel !== '' ? $q->where('channel', $channel) : $q;

        return $this->scope(
            Lead::query()
                ->whereHas('messages', $constraint)
                ->with('status')
                ->withMax(['messages as last_message_at' => $constraint], 'created_at')
        )
            ->orderByDesc('last_message_at')
            ->paginate(20);
    }

    public function latestMessage(Lead $lead)
    {
        return $lead->messages()
            ->when($this->channel !== '', fn ($q) => $q->where('channel', $this->channel))
            ->latest()
            ->first();
    }
}; ?>

@php
    $avatarPalette = ['bg-brand-600', 'bg-indigo-500', 'bg-emerald-500', 'bg-amber-500', 'bg-rose-500', 'bg-sky-500'];
@endphp

<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-6">
        <p class="eyebrow">Bandeja unificada</p>
        <h2 class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">Conversaciones</h2>
        <p class="text-sm text-muted">Correos y mensajes de WhatsApp con tus leads, en un solo lugar.</p>
    </div>

    {{-- Filtro por canal --}}
    <div class="mb-4 inline-flex gap-1 rounded-xl surface-muted p-1">
        @foreach (['' => 'Todas', 'email' => 'Correo', 'whatsapp' => 'WhatsApp'] as $value => $label)
            <button type="button" wire:click="$set('channel', '{{ $value }}')"
                    class="rounded-lg px-4 py-1.5 text-sm font-medium transition {{ $channel === $value ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="card divide-y divide-slate-100 overflow-hidden dark:divide-slate-800">
        @forelse ($this->conversations() as $i => $lead)
            @php($last = $this->latestMessage($lead))
            <a href="{{ route('leads.show', $lead) }}" wire:navigate
               class="flex items-center gap-3 px-4 py-3 transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-xs font-semibold text-white {{ $avatarPalette[$i % count($avatarPalette)] }}">
                    {{ \Illuminate\Support\Str::of($lead->full_name)->explode(' ')->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') ?: '?' }}
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        @if ($last?->channel === 'whatsapp')
                            <svg class="h-3.5 w-3.5 shrink-0 text-emerald-500" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.38a9.87 9.87 0 0 0 4.73 1.2h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.64-1.03-5.13-2.9-7A9.82 9.82 0 0 0 12.04 2Z"/></svg>
                        @else
                            <svg class="h-3.5 w-3.5 shrink-0 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        @endif
                        <p class="truncate font-medium text-slate-900 dark:text-slate-100">{{ $lead->full_name }}</p>
                        @if ($last)
                            <span class="ml-auto shrink-0 text-xs text-slate-400 dark:text-slate-500">{{ $last->created_at->diffForHumans(null, true) }}</span>
                        @endif
                    </div>
                    <p class="truncate text-sm text-slate-500 dark:text-slate-400">
                        @if ($last)
                            @if ($last->isOutbound())<span class="text-slate-400 dark:text-slate-500">Tú: </span>@endif
                            {{ \Illuminate\Support\Str::limit(strip_tags($last->body), 70) }}
                        @else
                            —
                        @endif
                    </p>
                </div>
            </a>
        @empty
            <div class="px-4 py-16 text-center">
                <svg class="mx-auto h-10 w-10 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12a8 8 0 01-11.6 7.1L3 21l1.9-6.4A8 8 0 1121 12z"/></svg>
                <p class="mt-3 text-sm text-muted">Aún no hay conversaciones{{ $channel === 'whatsapp' ? ' de WhatsApp' : ($channel === 'email' ? ' de correo' : '') }}.</p>
                <p class="text-xs text-muted">Responde a un lead desde su ficha para empezar.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $this->conversations()->links() }}
    </div>
</div>
