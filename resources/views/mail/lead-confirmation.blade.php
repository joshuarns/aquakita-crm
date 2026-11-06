@php($logo = $message->embedData(optional($company)->logoBytes() ?? \App\Support\BrandLogo::bytes(), 'logo.png', 'image/png'))
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
                        <td style="background:#ffffff;border-radius:14px;padding:34px 30px;">
                            <p style="margin:0;font-size:15px;line-height:1.7;color:#374151;">{!! nl2br(e($body)) !!}</p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:18px 10px 6px;color:#9ca3af;font-size:12px;line-height:1.6;">
                            {{ optional($company)->name ?? config('app.name', 'Aquakita') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
