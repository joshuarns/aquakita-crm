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

        $this->createLead($form, $data, $request);

        if (filled($form->redirect_url)) {
            return $this->renderForm($form, success: true, redirect: $form->redirect_url);
        }

        return $this->renderForm($form, success: true);
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

        // Aviso de lead nuevo a administradores y supervisores.
        app(LeadNotifier::class)->notifyNewLead($lead);

        $form->increment('submissions_count');

        return $lead;
    }
}
