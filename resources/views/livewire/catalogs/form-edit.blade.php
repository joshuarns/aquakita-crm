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
    public ?int $source_id = null;
    public ?int $campaign_id = null;
    public string $success_message = '';
    public string $redirect_url = '';
    public string $allowed_domains = '';
    public string $accent_color = '#0e7490';
    public bool $active = true;

    // Campos del widget flotante (solo aplican si display_mode = floating).
    public string $launcher_label = '';
    public string $launcher_icon = '';
    public string $launcher_avatar = '';
    public string $launcher_position = 'right';
    public string $whatsapp_number = '';

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
        $this->source_id = $form->source_id;
        $this->campaign_id = $form->campaign_id;
        $this->success_message = (string) $form->success_message;
        $this->redirect_url = (string) $form->redirect_url;
        $this->allowed_domains = collect($form->allowed_domains ?: [])->implode("\n");
        $this->accent_color = $form->accent_color ?: '#0e7490';
        $this->active = $form->active;

        $this->launcher_label = (string) $form->launcher_label;
        $this->launcher_icon = (string) $form->launcher_icon;
        $this->launcher_avatar = (string) $form->launcher_avatar;
        $this->launcher_position = $form->launcher_position ?: 'right';
        $this->whatsapp_number = (string) $form->whatsapp_number;

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
            'source_id' => ['nullable', 'exists:sources,id'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'success_message' => ['nullable', 'string', 'max:500'],
            'redirect_url' => ['nullable', 'url', 'max:255'],
            'accent_color' => ['required', 'string', 'max:20'],
            'launcher_label' => ['nullable', 'string', 'max:60'],
            'launcher_icon' => ['nullable', 'string', 'max:8'],
            'launcher_avatar' => ['nullable', 'url', 'max:500'],
            'launcher_position' => ['in:right,left'],
            'whatsapp_number' => ['nullable', 'string', 'max:25'],
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

        $this->form->update([
            'name' => $this->name,
            'source_id' => $this->source_id,
            'campaign_id' => $this->campaign_id,
            'fields' => $fields,
            'success_message' => $this->success_message ?: null,
            'redirect_url' => $this->redirect_url ?: null,
            'allowed_domains' => $domains ?: null,
            'accent_color' => $this->accent_color,
            'active' => $this->active,
            'launcher_label' => $this->launcher_label ?: null,
            'launcher_icon' => $this->launcher_icon ?: null,
            'launcher_avatar' => $this->launcher_avatar ?: null,
            'launcher_position' => $this->launcher_position,
            'whatsapp_number' => $this->whatsapp_number ?: null,
        ]);

        session()->flash('status', $this->form->isFloating() ? 'Widget guardado.' : 'Formulario guardado.');
    }

    public function with(): array
    {
        $token = $this->form->token;
        $showUrl = route('public.forms.show', $token);
        $frameId = 'aquakita-form-'.$token;

        $iframe = '<iframe src="'.$showUrl.'" id="'.$frameId.'" style="width:100%;border:0;overflow:hidden" height="560" scrolling="no" title="'.e($this->name).'"></iframe>'."\n"
            .'<script>window.addEventListener("message",function(e){if(!e.data||e.data.aquakitaForm!=="'.$token.'")return;var f=document.getElementById("'.$frameId.'");if(f&&e.data.height)f.height=e.data.height;if(e.data.redirect)window.top.location.href=e.data.redirect;});</script>';

        return [
            'scriptSnippet' => '<script src="'.route('public.forms.script', $token).'" async></script>',
            'iframeSnippet' => $iframe,
            'widgetSnippet' => '<script src="'.route('public.forms.widget', $token).'" async></script>',
            'previewUrl' => $showUrl,
        ];
    }
}; ?>

<div class="py-8" x-data="{ copied: null, copy(text, id) { navigator.clipboard.writeText(text).then(() => { this.copied = id; setTimeout(() => this.copied = null, 1500); }); } }">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
        @if ($form->isFloating())
            <a href="{{ route('widgets.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; Widgets flotantes</a>
        @else
            <a href="{{ route('forms.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; Formularios web</a>
        @endif

        @if (session('status'))
            <div class="my-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="mt-3 grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Configuración --}}
            <form wire:submit="save" class="lg:col-span-2 space-y-6">
                <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre del formulario</label>
                        <input type="text" wire:model="name" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Origen (Source)</label>
                            <select wire:model="source_id" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                                <option value="">— Sin origen —</option>
                                @foreach ($this->sources() as $source)
                                    <option value="{{ $source->id }}">{{ $source->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Campaña</label>
                            <select wire:model="campaign_id" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                                <option value="">— Sin campaña —</option>
                                @foreach ($this->campaigns() as $campaign)
                                    <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Campos --}}
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800">Campos del formulario</h3>
                    <p class="text-sm text-gray-500 mb-4">Activa los campos, márcalos como obligatorios y personaliza su etiqueta.</p>
                    <div class="space-y-2">
                        <div class="hidden sm:grid grid-cols-12 gap-2 text-xs font-medium text-gray-400 px-1">
                            <span class="col-span-2">Activo</span>
                            <span class="col-span-6">Etiqueta</span>
                            <span class="col-span-4">Obligatorio</span>
                        </div>
                        @php($fieldIcons = [
                            'first_name' => '👤', 'last_name' => '👤', 'company' => '🏢',
                            'email' => '✉️', 'phone' => '📞', 'project_type_id' => '🏗️',
                            'country_id' => '📍', 'description' => '💬',
                        ])
                        @foreach ($this->availableFields() as $key => $meta)
                            <div class="grid grid-cols-12 gap-2 items-center rounded-lg border border-gray-100 px-3 py-2">
                                <div class="col-span-2">
                                    <input type="checkbox" wire:model="fields.{{ $key }}.enabled"
                                           @disabled(! empty($meta['locked']))
                                           class="rounded border-gray-300 text-[#0e7490] focus:ring-[#0e7490]">
                                </div>
                                <div class="col-span-6">
                                    <input type="text" wire:model="fields.{{ $key }}.label"
                                           class="block w-full rounded-md border-gray-300 text-sm focus:border-[#0e7490] focus:ring-[#0e7490]">
                                    <p class="mt-1 text-xs text-gray-400">{{ $fieldIcons[$key] ?? '🔤' }} {{ $meta['label'] }}</p>
                                </div>
                                <div class="col-span-4 flex items-center gap-2">
                                    <input type="checkbox" wire:model="fields.{{ $key }}.required"
                                           @disabled(! empty($meta['locked']))
                                           class="rounded border-gray-300 text-[#0e7490] focus:ring-[#0e7490]">
                                    <span class="text-xs text-gray-500">Obligatorio</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Comportamiento --}}
                <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Mensaje de éxito</label>
                        <textarea wire:model="success_message" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]"
                                  placeholder="¡Gracias! Te contactaremos pronto."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Redirección tras enviar (opcional)</label>
                        <input type="url" wire:model="redirect_url" placeholder="https://tusitio.com/gracias"
                               class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                        @error('redirect_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Dominios permitidos (uno por línea)</label>
                        <textarea wire:model="allowed_domains" rows="2" placeholder="tusitio.com&#10;www.tusitio.com"
                                  class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]"></textarea>
                        <p class="mt-1 text-xs text-gray-400">Vacío = permitir en cualquier sitio.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Color</label>
                            <input type="color" wire:model="accent_color" class="mt-1 h-10 w-16 rounded border-gray-300">
                        </div>
                        <label class="flex items-center gap-2 mt-6">
                            <input type="checkbox" wire:model="active" class="rounded border-gray-300 text-[#0e7490] focus:ring-[#0e7490]">
                            <span class="text-sm text-gray-700">Formulario activo</span>
                        </label>
                    </div>
                </div>

                @if ($form->isFloating())
                    {{-- Ajustes del widget flotante --}}
                    <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                        <h3 class="font-semibold text-gray-800">Botón flotante y WhatsApp</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Mensaje de saludo</label>
                                <input type="text" wire:model="launcher_label" maxlength="60" placeholder="¿En qué podemos ayudarte?"
                                       class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                                <p class="mt-1 text-xs text-gray-400">Aparece en el globo junto al botón.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Posición</label>
                                <select wire:model="launcher_position" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                                    <option value="right">Abajo a la derecha</option>
                                    <option value="left">Abajo a la izquierda</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Avatar — URL de imagen (opcional)</label>
                            <input type="url" wire:model="launcher_avatar" placeholder="https://tusitio.com/agente.jpg"
                                   class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                            @error('launcher_avatar') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-gray-400">Foto de un asesor (recomendado, genera más confianza). Si lo dejas vacío, se usa el logo.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Número de WhatsApp para avisos</label>
                            <input type="text" wire:model="whatsapp_number" placeholder="+52 1 55 1234 5678"
                                   class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                            @error('whatsapp_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-gray-400">Con lada internacional (ej. +52...). Al enviar, el cliente verá un botón «Continuar por WhatsApp» que abre un chat hacia este número con sus datos ya escritos. Gratis, sin configuración extra.</p>
                        </div>
                    </div>
                @endif

                <button type="submit" class="rounded-lg bg-[#0e7490] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#155e75]">
                    Guardar cambios
                </button>
            </form>

            {{-- Código para incrustar --}}
            <div class="space-y-6">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800">Código para incrustar</h3>
                    <p class="text-sm text-gray-500 mb-4">Pega este código en tu sitio web.</p>

                    @if ($form->isFloating())
                        <p class="text-xs font-medium text-gray-500 mb-1">Botón flotante</p>
                        <div class="relative">
                            <pre class="rounded-lg bg-gray-900 p-3 text-xs text-gray-100 overflow-x-auto"><code>{{ $widgetSnippet }}</code></pre>
                            <button type="button" @click="copy(@js($widgetSnippet), 'widget')"
                                    class="absolute top-2 right-2 rounded bg-white/10 px-2 py-1 text-xs text-white hover:bg-white/20">
                                <span x-show="copied !== 'widget'">Copiar</span>
                                <span x-show="copied === 'widget'" x-cloak>¡Copiado!</span>
                            </button>
                        </div>
                        <p class="mt-3 text-xs text-gray-400">Pégalo antes de <code>&lt;/body&gt;</code>. Aparecerá el botón flotante en todo el sitio.</p>
                    @else
                        <p class="text-xs font-medium text-gray-500 mb-1">Opción 1 — Script (recomendado)</p>
                        <div class="relative">
                            <pre class="rounded-lg bg-gray-900 p-3 text-xs text-gray-100 overflow-x-auto"><code>{{ $scriptSnippet }}</code></pre>
                            <button type="button" @click="copy(@js($scriptSnippet), 'script')"
                                    class="absolute top-2 right-2 rounded bg-white/10 px-2 py-1 text-xs text-white hover:bg-white/20">
                                <span x-show="copied !== 'script'">Copiar</span>
                                <span x-show="copied === 'script'" x-cloak>¡Copiado!</span>
                            </button>
                        </div>

                        <p class="text-xs font-medium text-gray-500 mt-4 mb-1">Opción 2 — iframe</p>
                        <div class="relative">
                            <pre class="rounded-lg bg-gray-900 p-3 text-xs text-gray-100 overflow-x-auto"><code>{{ $iframeSnippet }}</code></pre>
                            <button type="button" @click="copy(@js($iframeSnippet), 'iframe')"
                                    class="absolute top-2 right-2 rounded bg-white/10 px-2 py-1 text-xs text-white hover:bg-white/20">
                                <span x-show="copied !== 'iframe'">Copiar</span>
                                <span x-show="copied === 'iframe'" x-cloak>¡Copiado!</span>
                            </button>
                        </div>
                    @endif

                    <a href="{{ $previewUrl }}" target="_blank" rel="noopener"
                       class="mt-4 inline-block text-sm text-[#0e7490] hover:underline">Ver formulario &rarr;</a>
                </div>
            </div>
        </div>
    </div>
</div>
