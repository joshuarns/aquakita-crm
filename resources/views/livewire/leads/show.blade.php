<?php

use App\Models\Activity;
use App\Models\DiscardReason;
use App\Models\Lead;
use App\Models\Status;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public Lead $lead;

    // --- Formulario de actividad (§6) ---
    public string $type = 'llamada';
    public string $comment = '';
    public string $result = '';
    public string $next_action = '';
    public string $follow_up_at = '';

    // --- Cambio de estatus (§7, §10) ---
    public ?int $new_status_id = null;
    public string $status_comment = '';
    public ?int $discard_reason_id = null;
    public string $sale_amount = '';

    // --- Archivos adjuntos (§5) ---
    public $upload = null;

    /**
     * Al abrir la ficha se registra la apertura del vendedor asignado (§4.3, §5).
     */
    public function mount(Lead $lead): void
    {
        $this->authorize('view', $lead);

        $this->lead = $lead;
        $this->new_status_id = $lead->status_id;

        $user = Auth::user();
        if ($user->hasRole('vendedor') && $lead->vendor_id === $user->id && $lead->first_opened_at === null) {
            $lead->forceFill(['first_opened_at' => now()])->save();

            $lead->notifications()
                ->where('vendor_id', $user->id)
                ->whereNull('opened_at')
                ->update(['opened_at' => now()]);

            $lead->recordTimeline('notification_opened', 'Notificación abierta', [
                'vendedor' => $user->name,
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function activityTypes(): array
    {
        return Activity::TYPES;
    }

    #[Computed]
    public function statuses()
    {
        return Status::where('active', true)->orderBy('order')->get();
    }

    #[Computed]
    public function discardReasons()
    {
        return DiscardReason::where('active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function timeline()
    {
        return $this->lead->timeline()->with('user')->latest()->get();
    }

    #[Computed]
    public function activities()
    {
        return $this->lead->activities()->with('performedBy')->latest()->get();
    }

    /**
     * Registra una actividad manual (§6). Marca el primer contacto real y
     * avanza el estatus de "Asignado" a "Primer contacto" (§7) cuando aplica.
     */
    public function addActivity(): void
    {
        $this->authorize('manage', $this->lead);

        $data = $this->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(Activity::TYPES))],
            'comment' => ['required', 'string'],
            'result' => ['nullable', 'string', 'max:255'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'follow_up_at' => ['nullable', 'date'],
        ]);

        $activity = $this->lead->activities()->create([
            'type' => $data['type'],
            'comment' => $data['comment'],
            'result' => $data['result'] ?: null,
            'next_action' => $data['next_action'] ?: null,
            'follow_up_at' => $data['follow_up_at'] ?: null,
            'performed_by' => Auth::id(),
        ]);

        // Primer contacto real (todo salvo la nota interna) marca los tiempos (§6, §8 KPI).
        $isContact = $data['type'] !== 'nota';
        if ($isContact && $this->lead->first_contact_at === null) {
            $this->lead->first_contact_at = now();

            $primerContacto = Status::where('order', 3)->first();
            if ($primerContacto && $this->lead->status?->order === 2) {
                $this->changeStatusTo($primerContacto, 'Primer contacto registrado');
            }
        }

        if ($data['follow_up_at']) {
            $this->lead->next_follow_up_at = $data['follow_up_at'];
            $this->lead->recordTimeline('follow_up', 'Seguimiento programado', [
                'fecha' => $data['follow_up_at'],
                'responsable' => Auth::user()->name,
            ]);
        }

        $this->lead->save();

        $this->lead->recordTimeline('contact', 'Contacto registrado', [
            'tipo' => Activity::TYPES[$data['type']],
            'resultado' => $activity->result,
        ]);

        $this->reset(['comment', 'result', 'next_action', 'follow_up_at']);
        $this->type = 'llamada';

        session()->flash('activity_status', 'Actividad registrada.');
    }

    /**
     * Cambia el estatus del lead con evidencia (§7, §10).
     * Cerrar o descartar exige motivo y comentario.
     */
    public function changeStatus(): void
    {
        $this->authorize('manage', $this->lead);

        $status = Status::findOrFail($this->new_status_id);

        $rules = ['status_comment' => ['nullable', 'string']];

        // Cierre/descarte requieren estatus, motivo y comentario (§10).
        if ($status->is_final && ! $status->is_won) {
            $rules['discard_reason_id'] = ['required', 'exists:discard_reasons,id'];
            $rules['status_comment'] = ['required', 'string'];
        }
        if ($status->is_won) {
            $rules['sale_amount'] = ['nullable', 'numeric', 'min:0'];
        }

        $this->validate($rules);

        if ($status->is_final && ! $status->is_won) {
            $this->lead->discard_reason_id = $this->discard_reason_id;
            $this->lead->discard_comment = $this->status_comment;
        }
        if ($status->is_won && $this->sale_amount !== '') {
            $this->lead->sale_amount = $this->sale_amount;
            $this->lead->sale_confirmed = true;
        }

        $this->changeStatusTo($status, $this->status_comment ?: null);
        $this->lead->save();

        $this->reset(['status_comment', 'discard_reason_id', 'sale_amount']);

        session()->flash('activity_status', "Estatus actualizado a «{$status->name}».");
    }

    /**
     * Aplica el cambio de estatus dejando historial y línea de tiempo (§5, §7).
     */
    private function changeStatusTo(Status $status, ?string $comment): void
    {
        $from = $this->lead->status;

        if ($from && $from->id === $status->id) {
            return;
        }

        $this->lead->statusHistories()->create([
            'from_status_id' => $from?->id,
            'to_status_id' => $status->id,
            'user_id' => Auth::id(),
            'comment' => $comment,
        ]);

        $this->lead->status_id = $status->id;
        $this->lead->setRelation('status', $status);

        $this->lead->recordTimeline('status_change', 'Cambio de estatus', [
            'anterior' => $from?->name,
            'nuevo' => $status->name,
            'usuario' => Auth::user()->name,
        ]);
    }

    /**
     * Sube un archivo adjunto a la ficha (§5). Guarda en disco privado y
     * registra el evento en la línea de tiempo.
     */
    public function addAttachment(): void
    {
        $this->authorize('manage', $this->lead);

        $this->validate([
            // Tipos y tamaño por confirmar con el cliente; se usan valores razonables.
            'upload' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,webp'],
        ]);

        $path = $this->upload->store('attachments/'.$this->lead->id, 'local');

        $this->lead->attachments()->create([
            'path' => $path,
            'original_name' => $this->upload->getClientOriginalName(),
            'mime_type' => $this->upload->getMimeType(),
            'size' => $this->upload->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        $this->lead->recordTimeline('attachment', 'Archivo adjuntado', [
            'archivo' => $this->upload->getClientOriginalName(),
            'usuario' => Auth::user()->name,
        ]);

        $this->reset('upload');
        session()->flash('activity_status', 'Archivo adjuntado.');
    }

    /** Elimina un adjunto y su archivo físico (§5). */
    public function deleteAttachment(int $attachmentId): void
    {
        $this->authorize('manage', $this->lead);

        $attachment = $this->lead->attachments()->findOrFail($attachmentId);
        \Illuminate\Support\Facades\Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        session()->flash('activity_status', 'Archivo eliminado.');
    }

    #[Computed]
    public function attachments()
    {
        return $this->lead->attachments()->with('uploadedBy')->latest()->get();
    }
}; ?>

<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8" wire:key="lead-{{ $lead->id }}">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-brand-600 text-sm font-semibold text-white">
                    {{ \Illuminate\Support\Str::of($lead->full_name)->explode(' ')->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') ?: '?' }}
                </span>
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $lead->full_name }}</h2>
                    <p class="text-sm text-muted">{{ $lead->company ?: __('Sin empresa') }}</p>
                </div>
            </div>
            <a href="{{ route('leads.index') }}" wire:navigate class="btn-secondary btn-sm">
                &larr; {{ __('Volver') }}
            </a>
        </div>

        @if (session('activity_status'))
            <div class="mb-4 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                {{ session('activity_status') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Columna izquierda: datos + acciones --}}
            <div class="lg:col-span-1 space-y-6">
                {{-- Datos del prospecto (§5) --}}
                <div class="card p-5 text-sm">
                    <h3 class="font-semibold text-slate-700 dark:text-slate-300 mb-3">{{ __('Datos') }}</h3>
                    <dl class="space-y-2">
                        <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">{{ __('Correo') }}</dt><dd class="text-slate-900 dark:text-slate-100">{{ $lead->email ?: '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">{{ __('Teléfono') }}</dt><dd class="text-slate-900 dark:text-slate-100">{{ $lead->phone ?: '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">{{ __('País') }}</dt><dd class="text-slate-900 dark:text-slate-100">{{ $lead->country?->name ?: '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">{{ __('Idioma') }}</dt><dd class="text-slate-900 dark:text-slate-100">{{ $lead->language?->name ?: '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">{{ __('Fuente') }}</dt><dd class="text-slate-900 dark:text-slate-100">{{ $lead->source?->name ?: '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">{{ __('Campaña') }}</dt><dd class="text-slate-900 dark:text-slate-100">{{ $lead->campaign?->name ?: '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">{{ __('Tipo de proyecto') }}</dt><dd class="text-slate-900 dark:text-slate-100">{{ $lead->projectType?->name ?: '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">{{ __('Vendedor') }}</dt><dd class="text-slate-900 dark:text-slate-100">{{ $lead->vendor?->name ?: '—' }}</dd></div>
                    </dl>
                    @if ($lead->description)
                        <p class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 text-slate-600 dark:text-slate-300">{{ $lead->description }}</p>
                    @endif
                </div>

                {{-- Estatus actual y próximo seguimiento --}}
                <div class="card p-5 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400">{{ __('Estatus') }}</span>
                        <span class="badge-brand">
                            {{ $lead->status?->name }}
                        </span>
                    </div>
                    @if ($lead->next_follow_up_at)
                        <div class="mt-2 flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">{{ __('Próximo seguimiento') }}</span>
                            <span class="text-slate-900 dark:text-slate-100">{{ $lead->next_follow_up_at->format('d/m/Y H:i') }}</span>
                        </div>
                    @endif
                </div>

                {{-- Archivos adjuntos (§5) --}}
                <div class="card p-5">
                    <h3 class="font-semibold text-slate-700 dark:text-slate-300 mb-3 text-sm">{{ __('Archivos adjuntos') }}</h3>

                    <ul class="space-y-2 mb-3">
                        @forelse ($this->attachments as $attachment)
                            <li class="flex items-center justify-between gap-2 text-sm">
                                <a href="{{ route('leads.attachments.download', [$lead, $attachment]) }}"
                                   class="truncate text-brand-600 hover:text-brand-700 dark:text-brand-400">
                                    {{ $attachment->original_name }}
                                </a>
                                @can('manage', $lead)
                                    <button wire:click="deleteAttachment({{ $attachment->id }})"
                                            wire:confirm="{{ __('¿Eliminar este archivo?') }}"
                                            class="text-xs text-slate-400 dark:text-slate-500 hover:text-red-600 shrink-0">✕</button>
                                @endcan
                            </li>
                        @empty
                            <li class="text-sm text-slate-500 dark:text-slate-400">{{ __('Sin archivos.') }}</li>
                        @endforelse
                    </ul>

                    @can('manage', $lead)
                        <form wire:submit="addAttachment" class="border-t border-slate-100 dark:border-slate-800 pt-3">
                            <input type="file" wire:model="upload" class="block w-full text-xs text-slate-600 dark:text-slate-300 file:mr-2 file:rounded file:border-0 file:bg-gray-800 file:px-3 file:py-1 file:text-white" />
                            <x-input-error :messages="$errors->get('upload')" class="mt-1" />
                            <div wire:loading wire:target="upload" class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Subiendo…') }}</div>
                            @if ($upload)
                                <x-primary-button class="mt-2">{{ __('Adjuntar') }}</x-primary-button>
                            @endif
                        </form>
                    @endcan
                </div>

                {{-- Cambio de estatus (§7, §10) --}}
                @can('manage', $lead)
                    <div class="card p-5">
                        <h3 class="font-semibold text-slate-700 dark:text-slate-300 mb-3 text-sm">{{ __('Cambiar estatus') }}</h3>
                        <form wire:submit="changeStatus" class="space-y-3">
                            <select wire:model.live="new_status_id" class="select text-sm">
                                @foreach ($this->statuses as $status)
                                    <option value="{{ $status->id }}">{{ $status->name }}</option>
                                @endforeach
                            </select>

                            @php($selected = $this->statuses->firstWhere('id', $new_status_id))
                            @if ($selected && $selected->is_final && ! $selected->is_won)
                                <div>
                                    <select wire:model="discard_reason_id" class="select text-sm">
                                        <option value="">{{ __('— Motivo de descarte —') }}</option>
                                        @foreach ($this->discardReasons as $reason)
                                            <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('discard_reason_id')" class="mt-1" />
                                </div>
                            @endif

                            @if ($selected && $selected->is_won)
                                <div>
                                    <x-text-input wire:model="sale_amount" type="number" step="0.01" class="block w-full text-sm" placeholder="{{ __('Monto de venta') }}" />
                                    <x-input-error :messages="$errors->get('sale_amount')" class="mt-1" />
                                </div>
                            @endif

                            <div>
                                <textarea wire:model="status_comment" rows="2" placeholder="{{ __('Comentario') }}" class="select text-sm"></textarea>
                                <x-input-error :messages="$errors->get('status_comment')" class="mt-1" />
                            </div>

                            <x-primary-button>{{ __('Actualizar estatus') }}</x-primary-button>
                        </form>
                    </div>
                @endcan
            </div>

            {{-- Columna derecha: actividad + línea de tiempo --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Registrar actividad (§6) --}}
                @can('manage', $lead)
                    <div class="card p-5">
                        <h3 class="font-semibold text-slate-700 dark:text-slate-300 mb-3 text-sm">{{ __('Registrar actividad') }}</h3>
                        <form wire:submit="addActivity" class="space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <x-input-label for="type" :value="__('Tipo de contacto')" />
                                    <select wire:model="type" id="type" class="select mt-1 text-sm">
                                        @foreach ($this->activityTypes() as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="result" :value="__('Resultado')" />
                                    <x-text-input wire:model="result" id="result" class="block mt-1 w-full text-sm" type="text" />
                                </div>
                            </div>
                            <div>
                                <x-input-label for="comment" :value="__('Comentario *')" />
                                <textarea wire:model="comment" id="comment" rows="2" class="select mt-1 text-sm"></textarea>
                                <x-input-error :messages="$errors->get('comment')" class="mt-1" />
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <x-input-label for="next_action" :value="__('Próxima acción')" />
                                    <x-text-input wire:model="next_action" id="next_action" class="block mt-1 w-full text-sm" type="text" />
                                </div>
                                <div>
                                    <x-input-label for="follow_up_at" :value="__('Siguiente seguimiento')" />
                                    <x-text-input wire:model="follow_up_at" id="follow_up_at" class="block mt-1 w-full text-sm" type="datetime-local" />
                                </div>
                            </div>
                            <x-primary-button>{{ __('Guardar actividad') }}</x-primary-button>
                        </form>
                    </div>
                @endcan

                {{-- Línea de tiempo completa (§5) --}}
                <div class="card p-5">
                    <h3 class="font-semibold text-slate-700 dark:text-slate-300 mb-4 text-sm">{{ __('Línea de tiempo') }}</h3>
                    <ol class="relative ms-3 space-y-6 border-s border-slate-200 dark:border-slate-700">
                        @forelse ($this->timeline as $event)
                            <li class="ms-6">
                                <span class="absolute -start-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-brand-100 ring-4 ring-white dark:bg-brand-900 dark:ring-slate-900"></span>
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $event->description ?: $event->type }}</p>
                                    <time class="text-xs text-slate-400 dark:text-slate-500">{{ $event->created_at->format('d/m/Y H:i') }}</time>
                                </div>
                                @if ($event->data)
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                        @foreach ($event->data as $k => $v)
                                            <span class="mr-2">{{ $k }}: <strong>{{ is_bool($v) ? ($v ? 'sí' : 'no') : $v }}</strong></span>
                                        @endforeach
                                    </p>
                                @endif
                                @if ($event->user)
                                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">{{ $event->user->name }}</p>
                                @endif
                            </li>
                        @empty
                            <li class="ms-6 text-sm text-slate-500 dark:text-slate-400">{{ __('Sin eventos todavía.') }}</li>
                        @endforelse
                    </ol>
                </div>
            </div>
        </div>
    </div>
