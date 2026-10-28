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

        $tk = $form->token;
        $src = route('public.forms.show', $tk);
        $color = $form->accent_color ?: '#0e7490';
        $avatar = $form->launcher_avatar ?: route('brand.logo');
        $greet = addslashes($form->launcher_label ?: '¿En qué podemos ayudarte?');
        $side = $form->launcher_position === 'left' ? 'left' : 'right';
        $title = addslashes(config('app.name', 'Aquakita'));

        $js = <<<JS
        (function () {
            if (document.getElementById('aqw-root-{$tk}')) { return; }
            var SIDE='{$side}', COLOR='{$color}', AVATAR='{$avatar}', GREET='{$greet}', TITLE='{$title}', SRC='{$src}';

            var st = document.createElement('style');
            st.textContent = '@keyframes aqwPulse{0%{transform:scale(1);opacity:.55}70%{transform:scale(1.8);opacity:0}100%{opacity:0}}'
                + '@keyframes aqwPop{from{transform:translateY(10px) scale(.92);opacity:0}to{transform:none;opacity:1}}'
                + '@keyframes aqwBob{0%,100%{transform:translateY(0)}50%{transform:translateY(-4px)}}';
            document.head.appendChild(st);

            var root = document.createElement('div');
            root.id = 'aqw-root-{$tk}';
            root.style.cssText = 'position:fixed;bottom:22px;'+SIDE+':22px;z-index:2147483000;display:flex;align-items:center;gap:12px;'
                + 'flex-direction:'+(SIDE==='left'?'row':'row-reverse')+';font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif';

            var bubble = document.createElement('div');
            bubble.style.cssText = 'display:none;position:relative;max-width:220px;background:#fff;color:#1f2937;padding:12px 30px 12px 14px;'
                + 'border-radius:16px;box-shadow:0 8px 28px rgba(0,0,0,.18);font-size:14px;font-weight:500;line-height:1.35;cursor:pointer;animation:aqwPop .3s ease';
            bubble.appendChild(document.createTextNode(GREET));
            var bclose = document.createElement('span');
            bclose.textContent = '×';
            bclose.style.cssText = 'position:absolute;top:5px;right:9px;color:#9ca3af;font-size:17px;line-height:1;cursor:pointer';
            bubble.appendChild(bclose);

            var fab = document.createElement('button');
            fab.type = 'button'; fab.setAttribute('aria-label', GREET);
            fab.style.cssText = 'position:relative;width:66px;height:66px;border-radius:50%;border:3px solid #fff;padding:0;cursor:pointer;'
                + 'background:'+COLOR+';box-shadow:0 10px 26px rgba(0,0,0,.3);flex:0 0 auto;transition:transform .15s;animation:aqwBob 3s ease-in-out infinite';
            var ring = document.createElement('span');
            ring.style.cssText = 'position:absolute;inset:-3px;border-radius:50%;background:'+COLOR+';z-index:-1;animation:aqwPulse 2.2s infinite';
            var img = document.createElement('img');
            img.src = AVATAR; img.alt = '';
            img.style.cssText = 'width:100%;height:100%;border-radius:50%;object-fit:cover;background:#fff;display:block';
            var dot = document.createElement('span');
            dot.style.cssText = 'position:absolute;bottom:3px;right:3px;width:15px;height:15px;border-radius:50%;background:#22c55e;border:2px solid #fff';
            fab.appendChild(ring); fab.appendChild(img); fab.appendChild(dot);
            fab.onmouseenter = function(){ fab.style.transform='scale(1.07)'; };
            fab.onmouseleave = function(){ fab.style.transform='none'; };

            root.appendChild(bubble); root.appendChild(fab);

            var panel = document.createElement('div');
            panel.style.cssText = 'position:fixed;bottom:24px;'+SIDE+':22px;z-index:2147483000;width:384px;max-width:calc(100vw - 32px);'
                + 'height:620px;max-height:calc(100vh - 48px);background:#fff;border-radius:18px;overflow:hidden;'
                + 'box-shadow:0 20px 56px rgba(0,0,0,.32);display:none;flex-direction:column';

            var bar = document.createElement('div');
            bar.style.cssText = 'display:flex;align-items:center;gap:10px;padding:12px 14px;background:'+COLOR+';color:#fff';
            var bav = document.createElement('img'); bav.src = AVATAR; bav.alt='';
            bav.style.cssText = 'width:40px;height:40px;border-radius:50%;object-fit:cover;background:#fff;border:2px solid rgba(255,255,255,.6)';
            var bti = document.createElement('div'); bti.style.cssText = 'flex:1;line-height:1.25';
            var tname = document.createElement('div'); tname.style.cssText='font-weight:600;font-size:14px'; tname.textContent = TITLE;
            var tsub = document.createElement('div'); tsub.style.cssText='font-size:11px;opacity:.9;display:flex;align-items:center;gap:5px';
            tsub.innerHTML = '<span style="width:8px;height:8px;border-radius:50%;background:#22c55e;display:inline-block"></span>En línea · normalmente responde en minutos';
            bti.appendChild(tname); bti.appendChild(tsub);
            var close = document.createElement('button');
            close.type='button'; close.setAttribute('aria-label','Cerrar'); close.innerHTML='&times;';
            close.style.cssText='border:0;background:transparent;color:#fff;font-size:26px;line-height:1;cursor:pointer;padding:0 4px';
            bar.appendChild(bav); bar.appendChild(bti); bar.appendChild(close);

            var iframe = document.createElement('iframe');
            iframe.src = SRC; iframe.title = 'Formulario de contacto';
            iframe.style.cssText = 'flex:1;width:100%;border:0;background:#fff';
            panel.appendChild(bar); panel.appendChild(iframe);

            function openPanel(){ panel.style.display='flex'; root.style.display='none'; }
            function hidePanel(){ panel.style.display='none'; root.style.display='flex'; }
            function hideBubble(){ bubble.style.display='none'; try{ sessionStorage.setItem('aqwG-{$tk}','1'); }catch(e){} }

            fab.onclick = openPanel;
            bubble.onclick = openPanel;
            bclose.onclick = function(e){ e.stopPropagation(); hideBubble(); };
            close.onclick = hidePanel;

            var dismissed = false; try{ dismissed = sessionStorage.getItem('aqwG-{$tk}')==='1'; }catch(e){}
            if (!dismissed) { setTimeout(function(){ bubble.style.display='block'; }, 2600); }

            window.addEventListener('message', function (e) {
                if (!e.data || e.data.aquakitaForm !== '{$tk}') { return; }
                // No cerramos el panel tras enviar: el usuario puede tocar «Continuar por WhatsApp».
                if (e.data.redirect) { window.top.location.href = e.data.redirect; }
            });

            document.body.appendChild(root);
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
     * Convierte el texto libre de campos de catálogo (tipo de proyecto, país) en
     * un id, creando el valor en el catálogo si no existe. Así el campo es de
     * texto abierto para el visitante, pero la ficha y los reportes siguen igual.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function resolveCatalogValues(LeadForm $form, array $data): array
    {
        foreach ($form->enabledFields() as $field) {
            $key = $field['key'];
            if (empty($field['resolve'])) {
                continue;
            }

            $value = trim((string) ($data[$key] ?? ''));
            if ($value === '') {
                unset($data[$key]);

                continue;
            }

            $catalog = $field['resolve'];
            $existing = $catalog::whereRaw('LOWER(name) = ?', [mb_strtolower($value)])->first();
            $data[$key] = $existing?->id ?? $catalog::create(['name' => $value])->id;
        }

        return $data;
    }

    /**
     * Crea el lead a partir de los datos validados.
     *
     * @param  array<string, mixed>  $data
     */
    protected function createLead(LeadForm $form, array $data, Request $request): Lead
    {
        $data = $this->resolveCatalogValues($form, $data);

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
