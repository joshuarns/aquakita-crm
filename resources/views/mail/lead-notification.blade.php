<x-mail::message>
{{ $body }}

<x-mail::button :url="config('app.url')">
Ingresar al sistema
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
