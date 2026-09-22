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
            </div>
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
