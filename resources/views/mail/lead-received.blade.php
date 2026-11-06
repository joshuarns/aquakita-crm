@php($logo = $message->embedData(optional($lead->company)->logoBytes() ?? \App\Support\BrandLogo::bytes(), 'logo.png', 'image/png'))
@php($rows = array_filter([
    'Nombre' => $lead->full_name,
    'Empresa' => $lead->company,
    'Email' => $lead->email,
    'Teléfono' => $lead->phone,
    'País' => $lead->country?->name,
    'Tipo de proyecto' => $lead->projectType?->name,
    'Descripción del proyecto' => $lead->description,
]))
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#eef2f5;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f5;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="width:100%;max-width:560px;">
                    <tr>
                        <td align="center" style="padding:10px 0 22px;">
                            <img src="{{ $logo }}" alt="Aquakita" width="150" style="display:block;width:150px;max-width:150px;height:auto;">
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#ffffff;border-radius:14px;padding:32px 30px;">
                            <p style="margin:0 0 26px;text-align:center;font-size:15px;font-weight:bold;line-height:1.5;color:#374151;text-transform:uppercase;">
                                Esta información fue enviada desde la página web.<br>Por favor ponte en contacto a la brevedad posible.
                            </p>
                            @foreach ($rows as $label => $value)
                                <p style="margin:0 0 16px;font-size:15px;line-height:1.5;color:#1f2937;">
                                    <strong>{{ $label }}:</strong> {{ $value }}
                                </p>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:18px 10px 6px;color:#9ca3af;font-size:12px;line-height:1.6;word-break:break-all;">
                            @if ($lead->source?->name)
                                {{ $lead->source->name }}@if ($lead->landing) · @endif
                            @endif
                            @if ($lead->landing)
                                <a href="{{ $lead->landing }}" style="color:#9ca3af;">{{ $lead->landing }}</a>
                            @endif
                            <br>
                            {{ $lead->created_at?->timezone(config('app.timezone'))->translatedFormat('d \d\e F \d\e Y, g:i a') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
