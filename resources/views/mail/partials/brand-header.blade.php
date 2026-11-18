{{-- Cabecera de marca del correo: logo propio de la empresa o, si no tiene, su nombre.
     Nunca cae al logo por defecto de la plataforma para no mezclar marcas. --}}
@if (optional($company)->logo)
    <img src="{{ $message->embedData($company->logoBytes(), 'logo.png', 'image/png') }}" alt="{{ $company->name }}" width="150" style="display:block;width:150px;max-width:150px;height:auto;margin:0 auto;">
@else
    <div style="font-size:22px;font-weight:700;letter-spacing:-0.01em;color:{{ optional($company)->brandColor() ?: '#0e7490' }};">{{ optional($company)->name ?? config('app.name') }}</div>
@endif
