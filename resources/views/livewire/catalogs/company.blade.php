<?php

use App\Models\Company;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public Company $company;

    public string $name = '';
    public string $brand_color = '#0e7490';
    public string $timezone = 'America/Mexico_City';
    public string $locale = 'es';
    public string $from_email = '';

    // Conversaciones por correo (responder + leer respuestas por IMAP)
    public bool $conversations_enabled = false;
    public string $reply_from_email = '';
    public string $imap_host = '';
    public ?int $imap_port = 993;
    public string $imap_username = '';
    public string $imap_password = ''; // solo escritura
    public string $imap_encryption = 'ssl';
    public string $imap_folder = 'INBOX';
    public bool $imap_validate_cert = true;
    public bool $has_imap_password = false;
    public ?array $imapTest = null;

    // WhatsApp (Cloud API)
    public bool $whatsapp_enabled = false;
    public string $whatsapp_number = '';
    public string $whatsapp_phone_number_id = '';
    public string $whatsapp_business_id = '';
    public string $whatsapp_token = ''; // solo escritura
    public string $whatsapp_verify_token = '';
    public bool $has_whatsapp_token = false;

    public $logoFile = null;

    public function mount(): void
    {
        $this->company = auth()->user()->company;
        abort_if($this->company === null, 404);

        $this->name = $this->company->name;
        $this->brand_color = $this->company->brand_color ?: '#0e7490';
        $this->timezone = $this->company->timezone ?: 'America/Mexico_City';
        $this->locale = $this->company->locale ?: 'es';
        $this->from_email = (string) $this->company->from_email;

        $this->conversations_enabled = (bool) $this->company->conversations_enabled;
        $this->reply_from_email = (string) $this->company->reply_from_email;
        $this->imap_host = (string) $this->company->imap_host;
        $this->imap_port = $this->company->imap_port ?: 993;
        $this->imap_username = (string) $this->company->imap_username;
        $this->imap_encryption = $this->company->imap_encryption ?: 'ssl';
        $this->imap_folder = $this->company->imap_folder ?: 'INBOX';
        $this->imap_validate_cert = (bool) $this->company->imap_validate_cert;
        $this->has_imap_password = filled($this->company->imap_password);

        $this->whatsapp_enabled = (bool) $this->company->whatsapp_enabled;
        $this->whatsapp_number = (string) $this->company->whatsapp_number;
        $this->whatsapp_phone_number_id = (string) $this->company->whatsapp_phone_number_id;
        $this->whatsapp_business_id = (string) $this->company->whatsapp_business_id;
        $this->whatsapp_verify_token = $this->company->whatsapp_verify_token ?: \Illuminate\Support\Str::random(20);
        $this->has_whatsapp_token = filled($this->company->whatsapp_token);
    }

    /**
     * Prueba la conexión IMAP con los datos del formulario (o la contraseña guardada).
     */
    public function testImap(): void
    {
        $password = filled($this->imap_password) ? $this->imap_password : (string) $this->company->imap_password;

        if (blank($this->imap_host) || blank($this->imap_username) || blank($password)) {
            $this->imapTest = ['ok' => false, 'msg' => 'Faltan servidor, usuario o contraseña.'];

            return;
        }

        try {
            $client = (new \Webklex\PHPIMAP\ClientManager)->make([
                'host' => $this->imap_host,
                'port' => $this->imap_port ?: 993,
                'encryption' => $this->imap_encryption === 'none' ? false : $this->imap_encryption,
                'validate_cert' => $this->imap_validate_cert,
                'username' => $this->imap_username,
                'password' => $password,
                'protocol' => 'imap',
            ]);
            $client->connect();
            $client->getFolder($this->imap_folder ?: 'INBOX');
            $client->disconnect();

            $this->imapTest = ['ok' => true, 'msg' => 'Conexión IMAP exitosa ✓'];
        } catch (\Throwable $e) {
            $this->imapTest = ['ok' => false, 'msg' => 'Error: '.$e->getMessage()];
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand_color' => ['required', 'string', 'max:20'],
            'timezone' => ['required', 'string', 'max:64'],
            'locale' => ['in:es,en,pt'],
            'from_email' => ['nullable', 'email', 'max:255'],
            'reply_from_email' => ['nullable', 'email', 'max:255'],
            'imap_host' => ['nullable', 'string', 'max:255'],
            'imap_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'imap_username' => ['nullable', 'string', 'max:255'],
            'imap_password' => ['nullable', 'string', 'max:255'],
            'imap_encryption' => ['nullable', 'in:ssl,tls,starttls,none'],
            'imap_folder' => ['nullable', 'string', 'max:100'],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:64'],
            'whatsapp_business_id' => ['nullable', 'string', 'max:64'],
            'whatsapp_token' => ['nullable', 'string', 'max:1024'],
            'whatsapp_verify_token' => ['nullable', 'string', 'max:64'],
            'logoFile' => ['nullable', 'image', 'max:1024'], // 1 MB
        ]);

        $data = [
            'name' => $this->name,
            'brand_color' => $this->brand_color,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'from_email' => $this->from_email ?: null,
            'conversations_enabled' => $this->conversations_enabled,
            'reply_from_email' => $this->reply_from_email ?: null,
            'imap_host' => $this->imap_host ?: null,
            'imap_port' => $this->imap_port ?: null,
            'imap_username' => $this->imap_username ?: null,
            'imap_encryption' => $this->imap_encryption ?: 'ssl',
            'imap_folder' => $this->imap_folder ?: 'INBOX',
            'imap_validate_cert' => $this->imap_validate_cert,
            'whatsapp_enabled' => $this->whatsapp_enabled,
            'whatsapp_number' => $this->whatsapp_number ?: null,
            'whatsapp_phone_number_id' => $this->whatsapp_phone_number_id ?: null,
            'whatsapp_business_id' => $this->whatsapp_business_id ?: null,
            'whatsapp_verify_token' => $this->whatsapp_verify_token ?: null,
        ];

        // Las credenciales solo se actualizan si se escribió una nueva (campos de solo escritura).
        if (filled($this->imap_password)) {
            $data['imap_password'] = $this->imap_password;
        }
        if (filled($this->whatsapp_token)) {
            $data['whatsapp_token'] = $this->whatsapp_token;
        }

        if ($this->logoFile) {
            $data['logo'] = base64_encode(file_get_contents($this->logoFile->getRealPath()));
        }

        $this->company->update($data);
        $this->logoFile = null;
        $this->imap_password = '';
        $this->whatsapp_token = '';
        $fresh = $this->company->fresh();
        $this->has_imap_password = filled($fresh->imap_password);
        $this->has_whatsapp_token = filled($fresh->whatsapp_token);

        session()->flash('status', 'Datos de la empresa guardados.');
    }
}; ?>

<div class="py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ route('catalogs.index') }}" wire:navigate class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:text-slate-300">&larr; Configuración</a>
        <h2 class="mt-1 text-xl font-semibold text-slate-800 dark:text-slate-100">Mi empresa</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Tu marca en correos, formularios y el sistema.</p>

        @if (session('status'))
            <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <form wire:submit="save" class="space-y-6 card p-6 shadow-sm">
            <div class="flex items-center gap-4">
                <img src="{{ $company->logoUrl() }}" alt="" class="h-16 w-16 rounded-xl object-contain surface-muted border border-slate-100 dark:border-slate-800">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Logo (PNG/JPG, máx. 1 MB)</label>
                    <input type="file" wire:model="logoFile" accept="image/*" class="mt-1 block text-sm text-slate-600 dark:text-slate-300">
                    <div wire:loading wire:target="logoFile" class="text-xs text-slate-400 dark:text-slate-500">Subiendo…</div>
                    @error('logoFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Nombre de la empresa</label>
                    <input type="text" wire:model="name" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Color de marca</label>
                    <input type="color" wire:model="brand_color" class="mt-1 h-10 w-16 rounded border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Zona horaria</label>
                    <input type="text" wire:model="timezone" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Idioma</label>
                    <select wire:model="locale" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                        <option value="es">Español</option>
                        <option value="en">English</option>
                        <option value="pt">Português</option>
                    </select>
                </div>
            </div>

            <div class="border-t border-slate-100 dark:border-slate-800 pt-5">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Correo del remitente (opcional)</label>
                <input type="email" wire:model="from_email" placeholder="{{ config('mail.from.address') }}"
                       class="mt-1 block w-full rounded-lg border-gray-300 focus:border-[#0e7490] focus:ring-[#0e7490]">
                @error('from_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Los correos salen con el <strong>nombre de tu empresa</strong>. Aquí puedes poner también tu propio correo «De».
                    <span class="text-amber-700">Importante:</span> el dominio de ese correo debe autorizar a este servidor (registro SPF); si no, los correos pueden caer en spam.
                    Déjalo vacío para usar el correo de la plataforma ({{ config('mail.from.address') }}).
                </p>
            </div>

            {{-- Conversaciones por correo (autoservicio) --}}
            <div class="border-t border-slate-100 pt-5 dark:border-slate-800">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Conversaciones por correo</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Responde a tus leads por correo y lee sus respuestas dentro del CRM.</p>
                    </div>
                    <label class="inline-flex shrink-0 items-center gap-2">
                        <input type="checkbox" wire:model.live="conversations_enabled" class="rounded border-slate-300 text-[#0e7490] focus:ring-[#0e7490]">
                        <span class="text-sm text-slate-700 dark:text-slate-300">Activar</span>
                    </label>
                </div>

                @if ($conversations_enabled)
                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="label">Correo del buzón (From / Reply-To)</label>
                            <input type="email" wire:model="reply_from_email" placeholder="info@tuempresa.com" class="input">
                            @error('reply_from_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-muted">Desde este correo se responde; las respuestas del lead llegan aquí (con un +token para casarlas).</p>
                        </div>

                        <div>
                            <label class="label">Proveedor del buzón</label>
                            <select class="select"
                                    @change="(() => { const p = {gmail:['imap.gmail.com',993,'ssl',true], outlook:['outlook.office365.com',993,'ssl',true], cpanel:['localhost',993,'ssl',false]}[$event.target.value]; if (p) { $wire.set('imap_host',p[0]); $wire.set('imap_port',p[1]); $wire.set('imap_encryption',p[2]); $wire.set('imap_validate_cert',p[3]); } })()">
                                <option value="">Elegir para autollenar…</option>
                                <option value="gmail">Gmail / Google Workspace</option>
                                <option value="outlook">Outlook / Microsoft 365</option>
                                <option value="cpanel">cPanel / Otro servidor IMAP</option>
                            </select>
                            <p class="mt-1 text-xs text-muted">Gmail y Outlook requieren una <strong>contraseña de aplicación</strong> (con verificación en 2 pasos activa).</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="label">Servidor IMAP</label>
                                <input type="text" wire:model="imap_host" placeholder="imap.gmail.com" class="input">
                                @error('imap_host') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Puerto</label>
                                <input type="number" wire:model="imap_port" placeholder="993" class="input">
                            </div>
                            <div>
                                <label class="label">Usuario IMAP</label>
                                <input type="text" wire:model="imap_username" placeholder="info@tuempresa.com" class="input">
                            </div>
                            <div>
                                <label class="label">
                                    Contraseña
                                    @if ($has_imap_password)<span class="text-xs font-normal text-emerald-600 dark:text-emerald-400">· configurada ✓</span>@endif
                                </label>
                                <input type="password" wire:model="imap_password" autocomplete="new-password"
                                       placeholder="{{ $has_imap_password ? 'Dejar vacío para no cambiarla' : 'Contraseña o App Password' }}" class="input">
                            </div>
                            <div>
                                <label class="label">Cifrado</label>
                                <select wire:model="imap_encryption" class="select">
                                    <option value="ssl">SSL</option>
                                    <option value="tls">TLS</option>
                                    <option value="starttls">STARTTLS</option>
                                    <option value="none">Ninguno</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Carpeta</label>
                                <input type="text" wire:model="imap_folder" placeholder="INBOX" class="input">
                            </div>
                        </div>

                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="imap_validate_cert" class="rounded border-slate-300 text-[#0e7490] focus:ring-[#0e7490]">
                            <span class="text-sm text-slate-700 dark:text-slate-300">Validar certificado <span class="text-muted">(desactívalo solo para localhost)</span></span>
                        </label>

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" wire:click="testImap" wire:loading.attr="disabled" wire:target="testImap" class="btn-secondary btn-sm">
                                <span wire:loading.remove wire:target="testImap">Probar conexión</span>
                                <span wire:loading wire:target="testImap">Probando…</span>
                            </button>
                            @if ($imapTest)
                                <span class="text-sm {{ $imapTest['ok'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">{{ $imapTest['msg'] }}</span>
                            @endif
                        </div>

                        <p class="text-xs text-muted">Guarda primero los datos; luego el cron <code>leads:fetch-replies</code> leerá este buzón cada pocos minutos y meterá las respuestas al hilo del lead.</p>
                    </div>
                @endif
            </div>

            {{-- WhatsApp (Cloud API) --}}
            <div class="border-t border-slate-100 pt-5 dark:border-slate-800">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                            <svg class="h-4 w-4 text-emerald-500" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.38a9.87 9.87 0 0 0 4.73 1.2h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.64-1.03-5.13-2.9-7A9.82 9.82 0 0 0 12.04 2Z"/></svg>
                            WhatsApp (Cloud API)
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Chatea con tus leads por WhatsApp desde el CRM (compatible con Coexistencia).</p>
                    </div>
                    <label class="inline-flex shrink-0 items-center gap-2">
                        <input type="checkbox" wire:model.live="whatsapp_enabled" class="rounded border-slate-300 text-[#0e7490] focus:ring-[#0e7490]">
                        <span class="text-sm text-slate-700 dark:text-slate-300">Activar</span>
                    </label>
                </div>

                @if ($whatsapp_enabled)
                    <div class="mt-4 space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="label">Número (visible)</label>
                                <input type="text" wire:model="whatsapp_number" placeholder="+52 55 1234 5678" class="input">
                            </div>
                            <div>
                                <label class="label">Phone Number ID</label>
                                <input type="text" wire:model="whatsapp_phone_number_id" placeholder="de Meta (API Setup)" class="input">
                            </div>
                            <div>
                                <label class="label">
                                    Token de acceso
                                    @if ($has_whatsapp_token)<span class="text-xs font-normal text-emerald-600 dark:text-emerald-400">· configurado ✓</span>@endif
                                </label>
                                <input type="password" wire:model="whatsapp_token" autocomplete="new-password"
                                       placeholder="{{ $has_whatsapp_token ? 'Dejar vacío para no cambiarlo' : 'Token permanente de Meta' }}" class="input">
                            </div>
                            <div>
                                <label class="label">WhatsApp Business ID (opcional)</label>
                                <input type="text" wire:model="whatsapp_business_id" placeholder="WABA ID" class="input">
                            </div>
                        </div>

                        <div class="rounded-lg surface-muted p-3 text-xs text-slate-600 dark:text-slate-300">
                            <p class="font-semibold text-slate-700 dark:text-slate-200">Configura esto en Meta (WhatsApp → Configuration → Webhooks):</p>
                            <div class="mt-2 space-y-1">
                                <p><span class="text-muted">Callback URL:</span> <code class="break-all">{{ route('whatsapp.receive') }}</code></p>
                                <p><span class="text-muted">Verify token:</span> <code class="break-all">{{ $whatsapp_verify_token }}</code></p>
                            </div>
                            <p class="mt-2 text-muted">Y suscríbete al campo <strong>messages</strong>. Guarda primero aquí para que el token quede activo.</p>
                        </div>
                        <input type="hidden" wire:model="whatsapp_verify_token">
                    </div>
                @endif
            </div>

            <button type="submit" class="btn-primary">
                Guardar cambios
            </button>
        </form>
    </div>
</div>
