<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $form->name }}</title>
    @php($accent = $form->accent_color ?: '#0e7490')
    <style>
        :root { --accent: {{ $accent }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1f2937; background: transparent; line-height: 1.5;
        }
        .aq-card {
            max-width: 520px; margin: 0 auto; padding: 24px;
            background: #ffffff; border: 1px solid #e5e7eb; border-radius: 14px;
        }
        .aq-field { margin-bottom: 16px; }
        .aq-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .aq-req { color: #dc2626; }
        .aq-input, .aq-select, .aq-textarea {
            width: 100%; padding: 10px 12px; font-size: 14px; color: #111827;
            border: 1px solid #d1d5db; border-radius: 9px; background: #fff; transition: border-color .15s, box-shadow .15s;
        }
        .aq-input:focus, .aq-select:focus, .aq-textarea:focus {
            outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 25%, transparent);
        }
        .aq-textarea { min-height: 96px; resize: vertical; }
        .aq-error { color: #dc2626; font-size: 12px; margin-top: 5px; }
        .aq-btn {
            width: 100%; padding: 12px 16px; font-size: 14px; font-weight: 600; color: #fff;
            background: var(--accent); border: 0; border-radius: 9px; cursor: pointer; transition: filter .15s;
        }
        .aq-btn:hover { filter: brightness(0.93); }
        .aq-alert { padding: 12px 14px; border-radius: 9px; font-size: 14px; margin-bottom: 16px; }
        .aq-alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .aq-success { text-align: center; padding: 24px 8px; }
        .aq-success svg { width: 48px; height: 48px; color: var(--accent); }
        .aq-success h2 { margin: 12px 0 4px; font-size: 18px; color: #111827; }
        .aq-success p { margin: 0; color: #6b7280; font-size: 14px; }
        .aq-wa-btn {
            display: inline-flex; align-items: center; gap: 8px; margin-top: 18px;
            padding: 11px 18px; border-radius: 9px; background: #25D366; color: #fff;
            font-size: 14px; font-weight: 600; text-decoration: none; transition: filter .15s;
        }
        .aq-wa-btn:hover { filter: brightness(0.95); }
        .aq-hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
    </style>
</head>
<body>
    <div class="aq-card">
        @if ($success)
            <div class="aq-success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <h2>¡Gracias!</h2>
                <p>{{ $form->success_message ?: 'Hemos recibido tus datos. Te contactaremos pronto.' }}</p>
                @if (! empty($whatsapp))
                    <a class="aq-wa-btn" href="{{ $whatsapp }}" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.38a9.87 9.87 0 0 0 4.73 1.2h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.64-1.03-5.13-2.9-7A9.82 9.82 0 0 0 12.04 2Zm5.8 14.17c-.25.69-1.44 1.32-1.98 1.37-.53.05-1.02.24-3.45-.72-2.9-1.15-4.76-4.12-4.9-4.31-.14-.19-1.18-1.57-1.18-3s.75-2.13 1.02-2.42c.27-.29.58-.36.78-.36l.56.01c.18.01.42-.07.66.5.25.6.84 2.06.91 2.2.07.14.12.31.02.5-.1.19-.15.31-.29.48-.14.17-.3.38-.43.5-.14.14-.29.29-.12.57.17.29.74 1.22 1.59 1.98 1.09.97 2.01 1.27 2.3 1.42.29.14.46.12.63-.07.17-.19.72-.84.91-1.13.19-.29.39-.24.66-.14.27.1 1.7.8 1.99.95.29.14.48.21.55.33.07.12.07.69-.18 1.37Z"/></svg>
                        Continuar por WhatsApp
                    </a>
                @endif
            </div>
            <script>
                try { window.parent.postMessage({ aquakitaForm: '{{ $form->token }}', submitted: true }, '*'); } catch (e) {}
            </script>
            @if ($redirect)
                <script>
                    (function () {
                        try {
                            window.parent.postMessage({ aquakitaForm: '{{ $form->token }}', redirect: '{{ $redirect }}' }, '*');
                        } catch (e) {}
                        setTimeout(function () { window.top.location.href = @json($redirect); }, 1200);
                    })();
                </script>
            @endif
        @else
            @if ($error)
                <div class="aq-alert aq-alert-error">{{ $error }}</div>
            @endif

            <form method="POST" action="{{ route('public.forms.submit', $form->token) }}" novalidate>
                <input type="hidden" name="_nonce" value="{{ $nonce }}">

                {{-- Honeypot: invisible para humanos, tentador para bots. --}}
                <div class="aq-hp" aria-hidden="true">
                    <label>No llenar este campo
                        <input type="text" name="website" tabindex="-1" autocomplete="off">
                    </label>
                </div>

                @foreach ($fields as $field)
                    @php($key = $field['key'])
                    @php($value = $old[$key] ?? '')
                    <div class="aq-field">
                        <label class="aq-label" for="aq-{{ $key }}">
                            {{ $field['label'] }}@if ($field['required'])<span class="aq-req"> *</span>@endif
                        </label>

                        @if ($field['type'] === 'textarea')
                            <textarea id="aq-{{ $key }}" name="{{ $key }}" class="aq-textarea" @if ($field['required']) required @endif>{{ $value }}</textarea>
                        @elseif ($field['type'] === 'select')
                            <select id="aq-{{ $key }}" name="{{ $key }}" class="aq-select" @if ($field['required']) required @endif>
                                <option value="">Selecciona…</option>
                                @foreach (($options[$key] ?? []) as $option)
                                    <option value="{{ $option->id }}" @selected((string) $value === (string) $option->id)>{{ $option->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="aq-{{ $key }}" name="{{ $key }}" type="{{ $field['type'] }}" class="aq-input"
                                   value="{{ $value }}" @if ($field['required']) required @endif>
                        @endif

                        @if (! empty($fieldErrors[$key]))
                            <div class="aq-error">{{ $fieldErrors[$key][0] }}</div>
                        @endif
                    </div>
                @endforeach

                <button type="submit" class="aq-btn">Enviar</button>
            </form>
        @endif
    </div>

    <script>
        (function () {
            var token = '{{ $form->token }}';
            function reportHeight() {
                try {
                    var h = document.body.scrollHeight;
                    window.parent.postMessage({ aquakitaForm: token, height: h }, '*');
                } catch (e) {}
            }
            window.addEventListener('load', reportHeight);
            window.addEventListener('resize', reportHeight);
            if (window.ResizeObserver) {
                new ResizeObserver(reportHeight).observe(document.body);
            }
            reportHeight();
        })();
    </script>
</body>
</html>
