<?php

use App\Models\Campaign;
use App\Models\LeadForm;
use App\Models\Source;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public LeadForm $form;

    public string $name = '';
    public string $locale = 'es';
    public ?int $source_id = null;
    public ?int $campaign_id = null;
    public string $success_message = '';
    public string $redirect_url = '';
    public string $allowed_domains = '';
    public string $notify_emails = '';
    public string $analytics_id = '';
    public string $accent_color = '#0e7490';

    // Estilo del formulario (fondo, borde, radios, padding).
    public string $bg_color = '#ffffff';
    public string $text_color = '#1f2937';
    public string $border_color = '#e5e7eb';
    public int $border_radius = 14;
    public int $padding = 24;
    public int $field_radius = 9;

    public string $submit_label = '';
    public string $confirmation_subject = '';
    public string $confirmation_body = '';
    public bool $active = true;

    // Campos del widget flotante (solo aplican si display_mode = floating).
    public string $launcher_label = '';
    public string $launcher_icon = '';
    public string $launcher_avatar = '';
    public string $launcher_position = 'right';
    public string $whatsapp_number = '';
    public string $whatsapp_label = '';

    /**
     * Configuración de campos, indexada por clave para enlazar en la vista.
     *
     * @var array<string, array{enabled: bool, required: bool, label: string}>
     */
    public array $fields = [];

    public function mount(LeadForm $form): void
    {
        $this->form = $form;
        $this->name = $form->name;
        $this->locale = $form->locale ?: 'es';
        $this->source_id = $form->source_id;
        $this->campaign_id = $form->campaign_id;
        $this->success_message = (string) $form->success_message;
        $this->redirect_url = (string) $form->redirect_url;
        $this->allowed_domains = collect($form->allowed_domains ?: [])->implode("\n");
        $this->notify_emails = collect($form->notify_emails ?: [])->implode("\n");
        $this->analytics_id = (string) $form->analytics_id;
        $this->accent_color = $form->accent_color ?: '#0e7490';

        $style = $form->style();
        $this->bg_color = $style['bg'];
        $this->text_color = $style['text'];
        $this->border_color = $style['border'];
        $this->border_radius = $style['radius'];
        $this->padding = $style['padding'];
        $this->field_radius = $style['field_radius'];

        $this->submit_label = (string) $form->submit_label;
        $this->confirmation_subject = (string) $form->confirmation_subject;
        $this->confirmation_body = (string) $form->confirmation_body;
        $this->active = $form->active;

        $this->launcher_label = (string) $form->launcher_label;
        $this->launcher_icon = (string) $form->launcher_icon;
        $this->launcher_avatar = (string) $form->launcher_avatar;
        $this->launcher_position = $form->launcher_position ?: 'right';
        $this->whatsapp_number = (string) $form->whatsapp_number;
        $this->whatsapp_label = (string) $form->whatsapp_label;

        $stored = collect($form->fields ?: LeadForm::defaultFields())->keyBy('key');
        foreach (LeadForm::availableFields() as $key => $meta) {
            $current = $stored->get($key, []);
            $this->fields[$key] = [
                'enabled' => (bool) ($current['enabled'] ?? false),
                'required' => (bool) ($current['required'] ?? false),
                'label' => $current['label'] ?? $meta['label'],
            ];
        }
    }

    /**
     * @return array<string, array{label: string, type: string, locked?: bool}>
     */
    public function availableFields(): array
    {
        return LeadForm::availableFields();
    }

    /**
     * @return Collection<int, Source>
     */
    public function sources(): Collection
    {
        return Source::orderBy('name')->get();
    }

    /**
     * @return Collection<int, Campaign>
     */
    public function campaigns(): Collection
    {
        return Campaign::orderBy('name')->get();
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['in:'.implode(',', array_keys(LeadForm::availableLocales()))],
            'source_id' => ['nullable', 'exists:sources,id'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'success_message' => ['nullable', 'string', 'max:500'],
            'redirect_url' => ['nullable', 'url', 'max:255'],
            'analytics_id' => ['nullable', 'string', 'max:40', 'regex:/^(G-|GT-|GTM-|AW-|UA-)[A-Z0-9-]+$/i'],
            'accent_color' => ['required', 'string', 'max:20'],
            'bg_color' => ['required', 'string', 'max:20'],
            'text_color' => ['required', 'string', 'max:20'],
            'border_color' => ['required', 'string', 'max:20'],
            'border_radius' => ['integer', 'min:0', 'max:60'],
            'padding' => ['integer', 'min:0', 'max:80'],
            'field_radius' => ['integer', 'min:0', 'max:40'],
            'submit_label' => ['nullable', 'string', 'max:60'],
            'confirmation_subject' => ['nullable', 'string', 'max:255'],
            'confirmation_body' => ['nullable', 'string', 'max:2000'],
            'launcher_label' => ['nullable', 'string', 'max:60'],
            'launcher_icon' => ['nullable', 'string', 'max:8'],
            'launcher_avatar' => ['nullable', 'url', 'max:500'],
            'launcher_position' => ['in:right,left'],
            'whatsapp_number' => ['nullable', 'string', 'max:25'],
            'whatsapp_label' => ['nullable', 'string', 'max:60'],
        ]);

        $available = LeadForm::availableFields();
        $fields = [];
        foreach ($available as $key => $meta) {
            $config = $this->fields[$key] ?? ['enabled' => false, 'required' => false, 'label' => $meta['label']];
            $enabled = ! empty($meta['locked']) ? true : (bool) ($config['enabled'] ?? false);
            $fields[] = [
                'key' => $key,
                'enabled' => $enabled,
                'required' => ! empty($meta['locked']) ? true : (bool) ($config['required'] ?? false),
                'label' => trim($config['label'] ?? '') ?: $meta['label'],
            ];
        }

        $domains = collect(preg_split('/\r\n|\r|\n/', $this->allowed_domains))
            ->map(fn ($d) => trim($d))
            ->filter()
            ->values()
            ->all();

        $emails = collect(preg_split('/\r\n|\r|\n|,/', $this->notify_emails))
            ->map(fn ($e) => trim($e))
            ->filter()
            ->unique()
            ->values()
            ->all();

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addError('notify_emails', "«{$email}» no es un correo válido.");

                return;
            }
        }

        $this->form->update([
            'name' => $this->name,
            'locale' => $this->locale,
            'source_id' => $this->source_id,
            'campaign_id' => $this->campaign_id,
            'fields' => $fields,
            'success_message' => $this->success_message ?: null,
            'redirect_url' => $this->redirect_url ?: null,
            'allowed_domains' => $domains ?: null,
            'notify_emails' => $emails ?: null,
            'analytics_id' => trim($this->analytics_id) ?: null,
            'accent_color' => $this->accent_color,
            'bg_color' => $this->bg_color,
            'text_color' => $this->text_color,
            'border_color' => $this->border_color,
            'border_radius' => $this->border_radius,
            'padding' => $this->padding,
            'field_radius' => $this->field_radius,
            'submit_label' => $this->submit_label ?: null,
            'confirmation_subject' => $this->confirmation_subject ?: null,
            'confirmation_body' => $this->confirmation_body ?: null,
            'active' => $this->active,
            'launcher_label' => $this->launcher_label ?: null,
            'launcher_icon' => $this->launcher_icon ?: null,
            'launcher_avatar' => $this->launcher_avatar ?: null,
            'launcher_position' => $this->launcher_position,
            'whatsapp_number' => $this->whatsapp_number ?: null,
            'whatsapp_label' => $this->whatsapp_label ?: null,
        ]);

        session()->flash('status', $this->form->isFloating() ? 'Widget guardado.' : 'Formulario guardado.');
    }

    public function with(): array
    {
        $token = $this->form->token;
        $showUrl = route('public.forms.show', $token);
        $frameId = 'aquakita-form-'.$token;

        $nameJs = addslashes($this->name);
        $iframe = '<iframe src="'.$showUrl.'" id="'.$frameId.'" style="width:100%;border:0;overflow:hidden" height="560" scrolling="no" title="'.e($this->name).'"></iframe>'."\n"
            .'<script>window.addEventListener("message",function(e){if(!e.data||e.data.aquakitaForm!=="'.$token.'")return;var f=document.getElementById("'.$frameId.'");if(f&&e.data.height)f.height=e.data.height;'
            .'if(e.data.submitted){try{window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:"aquakita_form_submit",form_id:"'.$token.'",form_name:"'.$nameJs.'"});if(typeof window.gtag==="function")window.gtag("event","generate_lead",{form_id:"'.$token.'",form_name:"'.$nameJs.'"});}catch(x){}}'
            .'if(e.data.redirect)window.top.location.href=e.data.redirect;});</script>';

        return [
            'scriptSnippet' => '<script src="'.route('public.forms.script', $token).'" async></script>',
            'iframeSnippet' => $iframe,
            'widgetSnippet' => '<script src="'.route('public.forms.widget', $token).'" async></script>',
            'previewUrl' => $showUrl,
        ];
    }
}; ?>

<div class="py-8" x-data="{ copied: null, copy(text, id) { navigator.clipboard.writeText(text).then(() => { this.copied = id; setTimeout(() => this.copied = null, 1500); }); } }">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Encabezado --}}
        <div>
            <a href="{{ $form->isFloating() ? route('widgets.index') : route('forms.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-sm font-medium text-muted hover:text-slate-700 dark:hover:text-slate-200">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                {{ $form->isFloating() ? 'Widgets flotantes' : 'Formularios web' }}
            </a>
        </div>

        <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="truncate text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $name ?: ($form->isFloating() ? 'Widget flotante' : 'Formulario web') }}</h1>
                    @if ($active)
                        <span class="badge-success">● Activo</span>
                    @else
                        <span class="badge-neutral">○ Inactivo</span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-muted">
                    {{ $form->isFloating() ? 'Botón flotante' : 'Formulario embebible' }}
                    · {{ \App\Models\LeadForm::availableLocales()[$locale] ?? $locale }}
                    · <span class="font-medium">{{ collect($fields)->where('enabled', true)->count() }}</span> campos activos
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="btn-secondary btn-sm">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    Ver formulario
                </a>
                <button type="button" wire:click="save" class="btn-primary btn-sm" wire:loading.attr="disabled">
                    <svg wire:loading.remove wire:target="save" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                    Guardar cambios
                </button>
            </div>
        </div>

        @if (session('status'))
            <div class="mt-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-300">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('status') }}
            </div>
        @endif

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            {{-- Configuración --}}
            <form wire:submit="save" class="lg:col-span-2 space-y-6">
                {{-- General --}}
                <section class="card card-pad space-y-5">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </span>
                        <div>
                            <h3 class="font-semibold text-slate-800 dark:text-slate-100">General</h3>
                            <p class="text-xs text-muted">Nombre, idioma y atribución del lead.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="label">Nombre del formulario</label>
                            <input type="text" wire:model="name" class="input">
                            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Idioma</label>
                            <select wire:model="locale" class="select">
                                @foreach (\App\Models\LeadForm::availableLocales() as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label">Origen (Source)</label>
                            <select wire:model="source_id" class="select">
                                <option value="">— Sin origen —</option>
                                @foreach ($this->sources() as $source)
                                    <option value="{{ $source->id }}">{{ $source->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Campaña</label>
                            <select wire:model="campaign_id" class="select">
                                <option value="">— Sin campaña —</option>
                                @foreach ($this->campaigns() as $campaign)
                                    <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <label class="flex items-center gap-3 rounded-xl surface-muted px-4 py-3">
                        <input type="checkbox" wire:model="active" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        <span>
                            <span class="block text-sm font-medium text-slate-700 dark:text-slate-200">Formulario activo</span>
                            <span class="block text-xs text-muted">Si lo desactivas, dejará de recibir envíos.</span>
                        </span>
                    </label>
                </section>

                {{-- Campos --}}
                <section class="card card-pad space-y-5">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
                        </span>
                        <div>
                            <h3 class="font-semibold text-slate-800 dark:text-slate-100">Campos del formulario</h3>
                            <p class="text-xs text-muted">Activa los campos, edita su etiqueta y marca cuáles son obligatorios.</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        @php($fieldIcons = [
                            'first_name' => '👤', 'last_name' => '👤', 'company' => '🏢',
                            'email' => '✉️', 'phone' => '📞', 'project_type_id' => '🏗️',
                            'country_id' => '📍', 'description' => '💬',
                        ])
                        @foreach ($this->availableFields() as $key => $meta)
                            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 dark:border-slate-800 px-3 py-2.5 sm:flex-nowrap">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg surface-muted text-base">{{ $fieldIcons[$key] ?? '🔤' }}</span>
                                <div class="min-w-0 flex-1">
                                    <input type="text" wire:model="fields.{{ $key }}.label" class="input !py-1.5 text-sm">
                                    <p class="mt-1 text-xs text-muted">
                                        {{ $meta['label'] }}
                                        @if (! empty($meta['locked']))
                                            <span class="badge-neutral ml-1 !py-0 text-[10px]">fijo</span>
                                        @endif
                                    </p>
                                </div>
                                <label class="flex shrink-0 items-center gap-1.5 text-xs font-medium text-slate-600 dark:text-slate-300">
                                    <input type="checkbox" wire:model="fields.{{ $key }}.enabled" @disabled(! empty($meta['locked']))
                                           class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 disabled:opacity-50">
                                    Mostrar
                                </label>
                                <label class="flex shrink-0 items-center gap-1.5 text-xs font-medium text-slate-600 dark:text-slate-300">
                                    <input type="checkbox" wire:model="fields.{{ $key }}.required" @disabled(! empty($meta['locked']))
                                           class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 disabled:opacity-50">
                                    Obligatorio
                                </label>
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- Estilo del formulario --}}
                <section class="card card-pad space-y-5">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                        </span>
                        <div>
                            <h3 class="font-semibold text-slate-800 dark:text-slate-100">Estilo del formulario</h3>
                            <p class="text-xs text-muted">Fondo, borde, redondeo y espaciado. Los cambios se ven al instante en la vista previa.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="label">Fondo</label>
                            <input type="color" wire:model.live="bg_color" class="h-10 w-full cursor-pointer rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent p-1">
                            @error('bg_color') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Texto</label>
                            <input type="color" wire:model.live="text_color" class="h-10 w-full cursor-pointer rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent p-1">
                            @error('text_color') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Borde</label>
                            <input type="color" wire:model.live="border_color" class="h-10 w-full cursor-pointer rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent p-1">
                            @error('border_color') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                        <div>
                            <label class="label flex justify-between">Redondeo del borde <span class="badge-brand !py-0">{{ $border_radius }}px</span></label>
                            <input type="range" min="0" max="40" wire:model.live="border_radius" class="mt-1 block w-full accent-brand-600">
                            @error('border_radius') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label flex justify-between">Espaciado interno <span class="badge-brand !py-0">{{ $padding }}px</span></label>
                            <input type="range" min="0" max="64" wire:model.live="padding" class="mt-1 block w-full accent-brand-600">
                            @error('padding') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label flex justify-between">Redondeo de campos <span class="badge-brand !py-0">{{ $field_radius }}px</span></label>
                            <input type="range" min="0" max="30" wire:model.live="field_radius" class="mt-1 block w-full accent-brand-600">
                            @error('field_radius') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label">Color principal (botón y foco)</label>
                            <input type="color" wire:model.live="accent_color" class="h-10 w-full cursor-pointer rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent p-1">
                        </div>
                    </div>

                    {{-- Vista previa en vivo --}}
                    <div>
                        <p class="eyebrow mb-2">Vista previa</p>
                        <div class="rounded-xl p-5" style="background:#f1f5f9;background-image:linear-gradient(45deg,#e2e8f0 25%,transparent 25%),linear-gradient(-45deg,#e2e8f0 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#e2e8f0 75%),linear-gradient(-45deg,transparent 75%,#e2e8f0 75%);background-size:16px 16px;background-position:0 0,0 8px,8px -8px,-8px 0;">
                            <div style="max-width:360px;margin:0 auto;background:{{ $bg_color }};color:{{ $text_color }};border:1px solid {{ $border_color }};border-radius:{{ $border_radius }}px;padding:{{ $padding }}px;">
                                <input type="text" placeholder="Nombre *" readonly
                                       style="width:100%;padding:10px 12px;font-size:14px;border:1px solid #d1d5db;border-radius:{{ $field_radius }}px;background:#fff;color:#111827;margin-bottom:12px;box-sizing:border-box;">
                                <input type="text" placeholder="Correo electrónico *" readonly
                                       style="width:100%;padding:10px 12px;font-size:14px;border:1px solid #d1d5db;border-radius:{{ $field_radius }}px;background:#fff;color:#111827;margin-bottom:12px;box-sizing:border-box;">
                                <button type="button" style="width:100%;padding:12px 16px;font-size:14px;font-weight:600;color:#fff;background:{{ $accent_color }};border:0;border-radius:{{ $field_radius }}px;cursor:default;">
                                    {{ $submit_label ?: \App\Models\LeadForm::strings($locale)['submit'] }}
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Mensajes y comportamiento --}}
                <section class="card card-pad space-y-5">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        </span>
                        <div>
                            <h3 class="font-semibold text-slate-800 dark:text-slate-100">Mensajes y comportamiento</h3>
                            <p class="text-xs text-muted">Qué ve y recibe el cliente al enviar el formulario.</p>
                        </div>
                    </div>

                    <div>
                        <label class="label">Mensaje de éxito</label>
                        <textarea wire:model="success_message" rows="2" class="textarea" placeholder="¡Gracias! Te contactaremos pronto."></textarea>
                    </div>
                    <div>
                        <label class="label">Texto del botón de envío</label>
                        <input type="text" wire:model="submit_label" maxlength="60" placeholder="{{ \App\Models\LeadForm::strings($locale)['submit'] }}" class="input">
                        <p class="mt-1 text-xs text-muted">Vacío = usa el texto por idioma («{{ \App\Models\LeadForm::strings($locale)['submit'] }}»). Aplica al formulario y al widget flotante.</p>
                        @error('submit_label') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="rounded-xl surface-muted p-4 space-y-3">
                        <div>
                            <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Correo de «gracias» al cliente</h4>
                            <p class="text-xs text-muted">Se envía al cliente cuando llena el formulario (si dejó su correo). Vacío = plantilla general de la empresa. Comodines: <code class="rounded bg-slate-200/70 dark:bg-slate-700 px-1">@{{lead}}</code> (nombre) y <code class="rounded bg-slate-200/70 dark:bg-slate-700 px-1">@{{negocio}}</code> (empresa).</p>
                        </div>
                        <div>
                            <label class="label">Asunto</label>
                            <input type="text" wire:model="confirmation_subject" maxlength="255" placeholder="¡Gracias por contactarnos! — @{{negocio}}" class="input">
                            @error('confirmation_subject') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Mensaje</label>
                            <textarea wire:model="confirmation_body" rows="5" maxlength="2000"
                                      placeholder="Hola @{{lead}},&#10;&#10;Gracias por contactarnos. Uno de nuestros asesores se pondrá en contacto contigo muy pronto.&#10;&#10;Saludos,&#10;Equipo @{{negocio}}"
                                      class="textarea"></textarea>
                            @error('confirmation_body') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label">Redirección tras enviar (opcional)</label>
                            <input type="url" wire:model="redirect_url" placeholder="https://tusitio.com/gracias" class="input">
                            @error('redirect_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Dominios permitidos (uno por línea)</label>
                            <textarea wire:model="allowed_domains" rows="2" placeholder="tusitio.com&#10;www.tusitio.com" class="textarea"></textarea>
                            <p class="mt-1 text-xs text-muted">Vacío = permitir en cualquier sitio.</p>
                        </div>
                    </div>
                </section>

                {{-- WhatsApp --}}
                <section class="card card-pad space-y-5">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38c1.45.79 3.08 1.21 4.79 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2zm5.8 14.03c-.24.68-1.42 1.3-1.95 1.35-.5.05-1.13.07-1.83-.11-.42-.13-.96-.31-1.66-.61-2.92-1.26-4.83-4.2-4.98-4.4-.15-.2-1.19-1.58-1.19-3.01 0-1.43.75-2.13 1.02-2.43.27-.3.59-.37.78-.37.2 0 .39 0 .56.01.18.01.42-.07.66.5.24.58.82 2.01.89 2.16.07.15.12.32.02.52-.1.2-.15.32-.3.49-.15.17-.31.39-.45.52-.15.15-.3.31-.13.6.17.3.76 1.25 1.63 2.02 1.12.99 2.06 1.3 2.36 1.45.3.15.47.12.64-.07.17-.2.74-.86.94-1.16.2-.3.4-.25.66-.15.27.1 1.7.8 1.99.95.3.15.5.22.57.35.07.12.07.72-.17 1.4z"/></svg>
                        </span>
                        <div>
                            <h3 class="font-semibold text-slate-800 dark:text-slate-100">Botón de WhatsApp al terminar</h3>
                            <p class="text-xs text-muted">Al enviar, el cliente ve un botón que abre WhatsApp hacia tu número con sus datos ya escritos. Vacío = no se muestra.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label">Número de WhatsApp</label>
                            <input type="text" wire:model="whatsapp_number" placeholder="+52 1 55 1234 5678" class="input">
                            @error('whatsapp_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-muted">Con lada internacional (ej. +52...).</p>
                        </div>
                        <div>
                            <label class="label">Texto del botón</label>
                            <input type="text" wire:model="whatsapp_label" maxlength="60" placeholder="{{ \App\Models\LeadForm::strings($locale)['whatsapp'] }}" class="input">
                            @error('whatsapp_label') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-muted">Vacío = «{{ \App\Models\LeadForm::strings($locale)['whatsapp'] }}».</p>
                        </div>
                    </div>
                </section>

                @if ($form->isFloating())
                    {{-- Ajustes del widget flotante --}}
                    <section class="card card-pad space-y-5">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                            </span>
                            <div>
                                <h3 class="font-semibold text-slate-800 dark:text-slate-100">Botón flotante</h3>
                                <p class="text-xs text-muted">Apariencia del lanzador en el sitio.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="label">Mensaje de saludo</label>
                                <input type="text" wire:model="launcher_label" maxlength="60" placeholder="¿En qué podemos ayudarte?" class="input">
                                <p class="mt-1 text-xs text-muted">Aparece en el globo junto al botón.</p>
                            </div>
                            <div>
                                <label class="label">Posición</label>
                                <select wire:model="launcher_position" class="select">
                                    <option value="right">Abajo a la derecha</option>
                                    <option value="left">Abajo a la izquierda</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="label">Avatar — URL de imagen (opcional)</label>
                            <input type="url" wire:model="launcher_avatar" placeholder="https://tusitio.com/agente.jpg" class="input">
                            @error('launcher_avatar') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-muted">Foto de un asesor (recomendado, genera más confianza). Vacío = usa el logo.</p>
                        </div>
                    </section>
                @endif

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">Guardar cambios</span>
                        <span wire:loading wire:target="save">Guardando…</span>
                    </button>
                </div>
            </form>

            {{-- Columna derecha --}}
            <div class="space-y-6 lg:sticky lg:top-6">
                {{-- Notificaciones y medición --}}
                <section class="card card-pad space-y-5">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </span>
                        <div>
                            <h3 class="font-semibold text-slate-800 dark:text-slate-100">Notificaciones y medición</h3>
                            <p class="text-xs text-muted">A quién avisar y cómo medir.</p>
                        </div>
                    </div>
                    <div>
                        <label class="label">Destinatarios del aviso (uno por línea)</label>
                        <textarea wire:model="notify_emails" rows="2" placeholder="ventas@tuempresa.com&#10;gerente@tuempresa.com" class="textarea"></textarea>
                        @error('notify_emails') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-muted">Vacío = administradores y supervisores.</p>
                    </div>
                    <div>
                        <label class="label">ID de Google Analytics / GTM (opcional)</label>
                        <input type="text" wire:model="analytics_id" placeholder="G-XXXXXXXXXX  o  GTM-XXXXXXX" class="input">
                        @error('analytics_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-muted">Mide el envío en la página del formulario. Si GA ya está en tu sitio, déjalo vacío.</p>
                    </div>
                    <button type="button" wire:click="save" class="btn-secondary btn-sm w-full">Guardar</button>
                </section>

                {{-- Código para incrustar --}}
                <section class="card card-pad">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                        </span>
                        <div>
                            <h3 class="font-semibold text-slate-800 dark:text-slate-100">Código para incrustar</h3>
                            <p class="text-xs text-muted">Pégalo en tu sitio web.</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-5">
                        @if ($form->isFloating())
                            <div>
                                <div class="mb-1.5 flex items-center justify-between">
                                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Botón flotante</span>
                                    <button type="button" @click="copy(@js($widgetSnippet), 'widget')" class="btn-secondary btn-sm">
                                        <span x-show="copied !== 'widget'">Copiar</span>
                                        <span x-show="copied === 'widget'" x-cloak>✓ Copiado</span>
                                    </button>
                                </div>
                                <pre class="overflow-x-auto rounded-xl bg-slate-900 p-3 text-xs leading-relaxed text-slate-100"><code>{{ $widgetSnippet }}</code></pre>
                                <p class="mt-2 text-xs text-muted">Pégalo antes de <code class="rounded bg-slate-100 dark:bg-slate-800 px-1">&lt;/body&gt;</code>. Aparece en todo el sitio.</p>
                            </div>
                        @else
                            <div>
                                <div class="mb-1.5 flex items-center justify-between">
                                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Opción 1 — Script <span class="badge-success !py-0 ml-1">recomendado</span></span>
                                    <button type="button" @click="copy(@js($scriptSnippet), 'script')" class="btn-secondary btn-sm">
                                        <span x-show="copied !== 'script'">Copiar</span>
                                        <span x-show="copied === 'script'" x-cloak>✓ Copiado</span>
                                    </button>
                                </div>
                                <pre class="overflow-x-auto rounded-xl bg-slate-900 p-3 text-xs leading-relaxed text-slate-100"><code>{{ $scriptSnippet }}</code></pre>
                            </div>
                            <div>
                                <div class="mb-1.5 flex items-center justify-between">
                                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Opción 2 — iframe</span>
                                    <button type="button" @click="copy(@js($iframeSnippet), 'iframe')" class="btn-secondary btn-sm">
                                        <span x-show="copied !== 'iframe'">Copiar</span>
                                        <span x-show="copied === 'iframe'" x-cloak>✓ Copiado</span>
                                    </button>
                                </div>
                                <pre class="overflow-x-auto rounded-xl bg-slate-900 p-3 text-xs leading-relaxed text-slate-100"><code>{{ $iframeSnippet }}</code></pre>
                            </div>
                        @endif

                        <a href="{{ $previewUrl }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">
                            Ver formulario
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
