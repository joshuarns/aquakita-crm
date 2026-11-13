<?php

use App\Models\LeadNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    /** Notificaciones no leídas del vendedor (§4.3 campanita). */
    #[Computed]
    public function unread()
    {
        return LeadNotification::query()
            ->where('vendor_id', Auth::id())
            ->whereNull('read_at')
            ->with('lead')
            ->latest()
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return LeadNotification::query()
            ->where('vendor_id', Auth::id())
            ->whereNull('read_at')
            ->count();
    }

    /** Marca leída y abre la ficha del lead. */
    public function open(int $notificationId): void
    {
        $notification = LeadNotification::where('vendor_id', Auth::id())->findOrFail($notificationId);
        $notification->update(['read_at' => now()]);

        $this->redirectRoute('leads.show', $notification->lead_id, navigate: true);
    }

    public function markAllRead(): void
    {
        LeadNotification::where('vendor_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        unset($this->unread, $this->unreadCount);
    }
}; ?>

<div class="relative" x-data="{ open: false }" wire:poll.30s>
    <button @click="open = ! open" class="relative inline-flex items-center rounded-lg p-2 text-slate-300 hover:bg-white/10 hover:text-white focus:outline-none">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        @if ($this->unreadCount > 0)
            <span class="absolute top-1 right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-white bg-red-600 rounded-full">
                {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
            </span>
        @endif
    </button>

    <div x-show="open" @click.outside="open = false" x-cloak
         class="absolute end-0 mt-2 w-80 bg-white rounded-md shadow-lg ring-1 ring-black/5 z-50">
        <div class="flex items-center justify-between px-4 py-2 border-b border-slate-100 dark:border-slate-800">
            <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('Notificaciones') }}</span>
            @if ($this->unreadCount > 0)
                <button wire:click="markAllRead" class="text-xs text-indigo-600 hover:text-indigo-800">
                    {{ __('Marcar todas leídas') }}
                </button>
            @endif
        </div>
        <ul class="max-h-80 overflow-y-auto divide-y divide-gray-50">
            @forelse ($this->unread as $notification)
                <li>
                    <button wire:click="open({{ $notification->id }})" class="w-full text-left px-4 py-3 hover:bg-slate-100 dark:hover:bg-slate-800/50">
                        <p class="text-sm text-slate-800 dark:text-slate-100">{{ $notification->message }}</p>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">{{ $notification->created_at->diffForHumans() }}</p>
                    </button>
                </li>
            @empty
                <li class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('Sin notificaciones nuevas.') }}</li>
            @endforelse
        </ul>
    </div>
</div>
