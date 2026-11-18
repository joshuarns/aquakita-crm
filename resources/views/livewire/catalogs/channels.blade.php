<?php

use App\Models\Company;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Company $company;

    public string $tab = 'email';

    // --- Correo (IMAP) ---
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

    // --- WhatsApp (Cloud API) ---
    public bool $whatsapp_enabled = false;
    public string $whatsapp_number = '';
    public string $whatsapp_phone_number_id = '';
    public string $whatsapp_business_id = '';
    public string $whatsapp_token = ''; // solo escritura
    public string $whatsapp_verify_token = '';
    public bool $has_whatsapp_token = false;

    public function mount(): void
    {
        $this->company = auth()->user()->company;
        abort_if($this->company === null, 404);

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

    public function saveEmail(): void
    {
        $this->validate([
            'reply_from_email' => ['nullable', 'email', 'max:255'],
            'imap_host' => ['nullable', 'string', 'max:255'],
            'imap_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'imap_username' => ['nullable', 'string', 'max:255'],
            'imap_password' => ['nullable', 'string', 'max:255'],
            'imap_encryption' => ['nullable', 'in:ssl,tls,starttls,none'],
            'imap_folder' => ['nullable', 'string', 'max:100'],
        ]);

        $data = [
            'conversations_enabled' => $this->conversations_enabled,
            'reply_from_email' => $this->reply_from_email ?: null,
            'imap_host' => $this->imap_host ?: null,
            'imap_port' => $this->imap_port ?: null,
            'imap_username' => $this->imap_username ?: null,
            'imap_encryption' => $this->imap_encryption ?: 'ssl',
            'imap_folder' => $this->imap_folder ?: 'INBOX',
            'imap_validate_cert' => $this->imap_validate_cert,
        ];

        if (filled($this->imap_password)) {
            $data['imap_password'] = $this->imap_password;
        }

        $this->company->update($data);
        $this->imap_password = '';
        $this->has_imap_password = filled($this->company->fresh()->imap_password);

        session()->flash('status', 'Configuración de correo guardada.');
    }

    public function saveWhatsapp(): void
    {
        $this->validate([
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:64'],
            'whatsapp_business_id' => ['nullable', 'string', 'max:64'],
            'whatsapp_token' => ['nullable', 'string', 'max:1024'],
            'whatsapp_verify_token' => ['nullable', 'string', 'max:64'],
        ]);

        $data = [
            'whatsapp_enabled' => $this->whatsapp_enabled,
            'whatsapp_number' => $this->whatsapp_number ?: null,
            'whatsapp_phone_number_id' => $this->whatsapp_phone_number_id ?: null,
            'whatsapp_business_id' => $this->whatsapp_business_id ?: null,
            'whatsapp_verify_token' => $this->whatsapp_verify_token ?: null,
        ];

        if (filled($this->whatsapp_token)) {
            $data['whatsapp_token'] = $this->whatsapp_token;
        }

        $this->company->update($data);
        $this->whatsapp_token = '';
        $this->has_whatsapp_token = filled($this->company->fresh()->whatsapp_token);

        session()->flash('status', 'Configuración de WhatsApp guardada.');
    }

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
}; ?>

<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
    <a href="{{ route('catalogs.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300">&larr; Configuración</a>
    <h2 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">Canales de conversación</h2>
    <p class="mb-6 text-sm text-muted">Responde a tus leads por correo y WhatsApp, y lee sus respuestas dentro del CRM.</p>

    @if (session('status'))
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            {{ session('status') }}
        </div>
    @endif

    {{-- Pestañas --}}
    <div class="mb-6 inline-flex gap-1 rounded-xl surface-muted p-1">
        <button type="button" wire:click="$set('tab', 'email')"
                class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition {{ $tab === 'email' ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white' }}">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            Correo
            @if ($conversations_enabled)<span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>@endif
        </button>
        <button type="button" wire:click="$set('tab', 'whatsapp')"
                class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition {{ $tab === 'whatsapp' ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white' }}">
            <svg class="h-4 w-4 text-emerald-500" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.38a9.87 9.87 0 0 0 4.73 1.2h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.64-1.03-5.13-2.9-7A9.82 9.82 0 0 0 12.04 2Z"/></svg>
            WhatsApp
            @if ($whatsapp_enabled)<span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>@endif
        </button>
    </div>

    @if ($tab === 'email')
        {{-- ====== Correo (IMAP) ====== --}}
        <form wire:submit="saveEmail" class="card space-y-5 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Conversaciones por correo</h3>
                    <p class="text-xs text-muted">Responde por correo y lee las respuestas del lead en su ficha.</p>
                </div>
                <label class="inline-flex shrink-0 items-center gap-2">
                    <input type="checkbox" wire:model.live="conversations_enabled" class="rounded border-slate-300 text-[#0e7490] focus:ring-[#0e7490]">
                    <span class="text-sm text-slate-700 dark:text-slate-300">Activar</span>
                </label>
            </div>

            @if ($conversations_enabled)
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
            @endif

            <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                <button type="submit" class="btn-primary">Guardar correo</button>
            </div>
        </form>
    @else
        {{-- ====== WhatsApp ====== --}}
        <form wire:submit="saveWhatsapp" class="card space-y-5 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">WhatsApp (Cloud API)</h3>
                    <p class="text-xs text-muted">Chatea con tus leads por WhatsApp desde el CRM (compatible con Coexistencia: el número sigue en el celular).</p>
                </div>
                <label class="inline-flex shrink-0 items-center gap-2">
                    <input type="checkbox" wire:model.live="whatsapp_enabled" class="rounded border-slate-300 text-[#0e7490] focus:ring-[#0e7490]">
                    <span class="text-sm text-slate-700 dark:text-slate-300">Activar</span>
                </label>
            </div>

            @if ($whatsapp_enabled)
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
            @endif

            <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                <button type="submit" class="btn-primary">Guardar WhatsApp</button>
            </div>
        </form>
    @endif
</div>
