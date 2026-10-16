<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadForm;
use App\Models\Status;
use App\Support\LeadNotifier;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Endpoints públicos (sin autenticación) de los formularios web embebibles.
 */
class LeadFormController extends Controller
{
    /**
     * Página autónoma del formulario, pensada para incrustarse en un iframe.
     */
    public function show(string $token): View|Response
    {
        $form = $this->resolveForm($token);

        return $this->renderForm($form);
    }

    /**
     * Snippet de JavaScript que inserta el iframe y auto-ajusta su altura.
     */
    public function script(string $token): Response
    {
        $form = $this->resolveForm($token);

        $src = route('public.forms.show', $form->token);
        $frameId = 'aquakita-form-'.$form->token;

        $js = <<<JS
        (function () {
            var s = document.currentScript;
            var iframe = document.createElement('iframe');
            iframe.src = '{$src}';
            iframe.id = '{$frameId}';
            iframe.style.width = '100%';
            iframe.style.border = '0';
            iframe.style.overflow = 'hidden';
            iframe.setAttribute('scrolling', 'no');
            iframe.setAttribute('title', 'Formulario de contacto');
            iframe.height = '560';
            if (s && s.parentNode) {
                s.parentNode.insertBefore(iframe, s);
            } else {
                document.body.appendChild(iframe);
            }
            window.addEventListener('message', function (e) {
                if (!e.data || e.data.aquakitaForm !== '{$form->token}') {
                    return;
                }
                if (e.data.height) {
                    iframe.height = e.data.height;
                }
                if (e.data.redirect) {
                    window.top.location.href = e.data.redirect;
                }
            });
        })();
        JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /**
     * Script del widget flotante: inserta un botón fijo que abre el formulario
     * en un panel emergente.
     */
    public function widget(string $token): Response
    {
        $form = $this->resolveForm($token);

        $src = route('public.forms.show', $form->token);
        $color = $form->accent_color ?: '#0e7490';
        $icon = $form->launcher_icon ?: '💬';
        $label = addslashes($form->launcher_label ?: '¿Hablamos?');
        $side = $form->launcher_position === 'left' ? 'left' : 'right';

        $js = <<<JS
        (function () {
            if (document.getElementById('aqw-{$form->token}')) { return; }
            var SIDE = '{$side}';
            var COLOR = '{$color}';

            var btn = document.createElement('button');
            btn.id = 'aqw-{$form->token}';
            btn.type = 'button';
            btn.setAttribute('aria-label', '{$label}');
            btn.innerHTML = '<span style="font-size:22px;line-height:1">{$icon}</span><span class="aqw-label" style="font-weight:600;font-size:14px">{$label}</span>';
            btn.style.cssText = 'position:fixed;bottom:20px;'+SIDE+':20px;z-index:2147483000;display:flex;align-items:center;gap:8px;'
                + 'padding:12px 18px;border:0;border-radius:999px;cursor:pointer;color:#fff;background:'+COLOR+';'
                + 'box-shadow:0 8px 24px rgba(0,0,0,.25);font-family:system-ui,-apple-system,sans-serif;transition:transform .15s';
            btn.onmouseenter = function(){ btn.style.transform='translateY(-2px)'; };
            btn.onmouseleave = function(){ btn.style.transform='none'; };

            var panel = document.createElement('div');
            panel.style.cssText = 'position:fixed;bottom:84px;'+SIDE+':20px;z-index:2147483000;width:380px;max-width:calc(100vw - 32px);'
                + 'height:600px;max-height:calc(100vh - 110px);background:#fff;border-radius:16px;overflow:hidden;'
                + 'box-shadow:0 16px 48px rgba(0,0,0,.3);display:none;flex-direction:column';

            var bar = document.createElement('div');
            bar.style.cssText = 'display:flex;justify-content:flex-end;align-items:center;padding:8px 10px;background:'+COLOR;
            var close = document.createElement('button');
            close.type='button'; close.setAttribute('aria-label','Cerrar'); close.innerHTML='&times;';
            close.style.cssText='border:0;background:transparent;color:#fff;font-size:24px;line-height:1;cursor:pointer;padding:2px 6px';
            bar.appendChild(close);

            var iframe = document.createElement('iframe');
            iframe.src = '{$src}';
            iframe.title = 'Formulario de contacto';
            iframe.style.cssText = 'flex:1;width:100%;border:0;background:#fff';

            panel.appendChild(bar); panel.appendChild(iframe);

            function open(){ panel.style.display='flex'; btn.style.display='none'; }
            function hide(){ panel.style.display='none'; btn.style.display='flex'; }
            btn.onclick = open; close.onclick = hide;

            window.addEventListener('message', function (e) {
                if (!e.data || e.data.aquakitaForm !== '{$form->token}') { return; }
                if (e.data.submitted) { setTimeout(hide, 2500); }
                if (e.data.redirect) { window.top.location.href = e.data.redirect; }
            });

            document.body.appendChild(btn);
            document.body.appendChild(panel);
        })();
        JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /**
     * Recibe el envío del formulario y crea el lead.
     */
    public function submit(Request $request, string $token): View|RedirectResponse|Response
    {
        $form = $this->resolveForm($token);

        // Honeypot: si el campo trampa viene lleno, es un bot. Fingimos éxito.
        if (filled($request->input('website'))) {
            return $this->renderForm($form, success: true);
        }

        if (! $this->originAllowed($request, $form)) {
            abort(403, 'Este formulario no está autorizado para este dominio.');
        }

        if (! $this->passesRateLimit($request, $form)) {
            return $this->renderForm($form, error: 'Demasiados envíos. Intenta de nuevo en un momento.', status: 429);
        }

        if (! $this->nonceIsValid($request->input('_nonce'))) {
            return $this->renderForm($form, error: 'La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.', status: 422);
        }

        try {
            $data = $this->validated($request, $form);
        } catch (ValidationException $e) {
            return $this->renderForm($form, errors: $e->errors(), old: $request->all(), status: 422);
        }

        $lead = $this->createLead($form, $data, $request);

        $whatsapp = $this->whatsAppHandoffUrl($form, $lead);

        if (filled($form->redirect_url)) {
            return $this->renderForm($form, success: true, redirect: $form->redirect_url, whatsapp: $whatsapp);
        }

        return $this->renderForm($form, success: true, whatsapp: $whatsapp);
    }

    /**
     * Arma el enlace wa.me para que el cliente continúe la conversación por
     * WhatsApp con el número del formulario, con sus datos ya escritos.
     */
    protected function whatsAppHandoffUrl(LeadForm $form, Lead $lead): ?string
    {
        $number = preg_replace('/[^0-9]/', '', (string) $form->whatsapp_number);
        if (blank($number)) {
            return null;
        }

        $lines = ['Hola, soy '.$lead->full_name.'.'];
        if ($lead->phone) {
            $lines[] = 'Tel: '.$lead->phone;
        }
        if ($lead->email) {
            $lines[] = 'Correo: '.$lead->email;
        }
        if ($lead->description) {
            $lines[] = $lead->description;
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode(implode("\n", $lines));
    }

    /**
     * Localiza un formulario activo por su token o lanza 404.
     */
    protected function resolveForm(string $token): LeadForm
    {
        return LeadForm::where('token', $token)->where('active', true)->firstOrFail();
    }

    /**
     * Renderiza la vista del formulario con las cabeceras de incrustación.
     *
     * @param  array<string, string>  $errors
     * @param  array<string, mixed>  $old
     */
    protected function renderForm(
        LeadForm $form,
        bool $success = false,
        ?string $error = null,
        array $errors = [],
        array $old = [],
        ?string $redirect = null,
        ?string $whatsapp = null,
        int $status = 200,
    ): Response {
        $view = view('public.lead-form', [
            'form' => $form,
            'fields' => $form->enabledFields(),
            'options' => $this->catalogOptions($form),
            'nonce' => Crypt::encryptString((string) now()->timestamp),
            'success' => $success,
            'error' => $error,
            'fieldErrors' => $errors,
            'old' => $old,
            'redirect' => $redirect,
            'whatsapp' => $whatsapp,
        ]);

        return response($view->render(), $status)
            ->withHeaders($this->frameHeaders($form));
    }

    /**
     * Opciones para los campos de tipo select (catálogos).
     *
     * @return array<string, Collection<int, object>>
     */
    protected function catalogOptions(LeadForm $form): array
    {
        $options = [];
        foreach ($form->enabledFields() as $field) {
            if ($field['type'] === 'select' && $field['catalog']) {
                $options[$field['key']] = $field['catalog']::query()
                    ->when(
                        Schema::hasColumn((new $field['catalog'])->getTable(), 'active'),
                        fn ($q) => $q->where('active', true)
                    )
                    ->orderBy('name')
                    ->get(['id', 'name']);
            }
        }

        return $options;
    }

    /**
     * Cabeceras que permiten (o restringen) la incrustación en iframe.
     *
     * @return array<string, string>
     */
    protected function frameHeaders(LeadForm $form): array
    {
        $domains = $form->allowed_domains ?: [];

        if (empty($domains)) {
            $ancestors = '*';
        } else {
            $ancestors = collect($domains)
                ->map(fn ($d) => $this->normalizeAncestor($d))
                ->filter()
                ->implode(' ');
            $ancestors = $ancestors !== '' ? "'self' {$ancestors}" : "'self'";
        }

        return [
            'Content-Security-Policy' => "frame-ancestors {$ancestors}",
            'X-Frame-Options' => empty($domains) ? '' : 'ALLOW-FROM '.$this->normalizeAncestor($domains[0]),
        ];
    }

    protected function normalizeAncestor(string $domain): string
    {
        $domain = trim($domain);
        if ($domain === '') {
            return '';
        }
        if (! Str::startsWith($domain, ['http://', 'https://'])) {
            $domain = 'https://'.$domain;
        }

        return rtrim($domain, '/');
    }

    /**
     * Comprueba que el envío provenga de un dominio autorizado (si se configuró alguno).
     */
    protected function originAllowed(Request $request, LeadForm $form): bool
    {
        $domains = $form->allowed_domains ?: [];
        if (empty($domains)) {
            return true;
        }

        $origin = $request->headers->get('origin') ?: $request->headers->get('referer');
        if (! $origin) {
            // Sin cabecera de origen no podemos verificar; permitimos envíos directos
            // desde la propia página del formulario (mismo host).
            return true;
        }

        $originHost = parse_url($origin, PHP_URL_HOST);

        foreach ($domains as $domain) {
            $allowedHost = parse_url($this->normalizeAncestor($domain), PHP_URL_HOST);
            if ($allowedHost && $originHost && Str::is($allowedHost, $originHost)) {
                return true;
            }
        }

        return $originHost === $request->getHost();
    }

    protected function passesRateLimit(Request $request, LeadForm $form): bool
    {
        $key = 'lead-form:'.$form->id.':'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 8)) {
            return false;
        }

        RateLimiter::hit($key, decaySeconds: 60);

        return true;
    }

    protected function nonceIsValid(?string $nonce): bool
    {
        if (! $nonce) {
            return false;
        }

        try {
            $issuedAt = (int) Crypt::decryptString($nonce);
        } catch (DecryptException) {
            return false;
        }

        // Válido hasta 6 horas después de renderizar el formulario.
        $age = now()->timestamp - $issuedAt;

        return $age >= 0 && $age <= 21600;
    }

    /**
     * Valida el envío según los campos activos del formulario.
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request, LeadForm $form): array
    {
        $rules = [];
        $attributes = [];

        foreach ($form->enabledFields() as $field) {
            $key = $field['key'];
            $rule = [$field['required'] ? 'required' : 'nullable'];

            $rule[] = match ($field['type']) {
                'email' => 'email',
                'select' => 'integer',
                'textarea' => 'string',
                default => 'string',
            };

            if ($field['type'] === 'select' && $field['catalog']) {
                $table = (new $field['catalog'])->getTable();
                $rule[] = "exists:{$table},id";
            } elseif ($field['type'] !== 'textarea') {
                $rule[] = 'max:255';
            }

            $rules[$key] = $rule;
            $attributes[$key] = mb_strtolower($field['label']);
        }

        return $request->validate($rules, [], $attributes);
    }

    /**
     * Crea el lead a partir de los datos validados.
     *
     * @param  array<string, mixed>  $data
     */
    protected function createLead(LeadForm $form, array $data, Request $request): Lead
    {
        $initial = Status::where('order', 1)->firstOrFail();

        $lead = Lead::create([
            ...$data,
            'status_id' => $initial->id,
            'source_id' => $form->source_id,
            'campaign_id' => $form->campaign_id,
            'lead_form_id' => $form->id,
            'landing' => Str::limit((string) $request->headers->get('referer'), 250, ''),
            'captured_by' => null,
        ]);

        $lead->recordTimeline('captured', "Lead capturado desde el formulario «{$form->name}»", [
            'formulario' => $form->name,
            'ip' => $request->ip(),
        ], userId: null);

        // Aviso de lead nuevo a administradores y supervisores (correo + campanita).
        app(LeadNotifier::class)->notifyNewLead($lead);

        $form->increment('submissions_count');

        return $lead;
    }
}
