<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen lg:grid lg:grid-cols-2">
            {{-- Brand panel --}}
            <div class="relative hidden overflow-hidden bg-[#0e2a33] p-10 text-white lg:flex lg:flex-col lg:justify-between xl:p-14">
                <div aria-hidden="true"
                     class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-[#12b0c4]/10 blur-3xl"></div>
                <div aria-hidden="true"
                     class="pointer-events-none absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-[#0e7490]/20 blur-3xl"></div>

                {{-- Logo --}}
                <a href="/" wire:navigate class="relative z-10 inline-block">
                    <img src="{{ route('brand.logo') }}" alt="Aquakita" class="h-20 w-auto drop-shadow-lg">
                </a>

                {{-- Tagline --}}
                <div class="relative z-10">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#3bc9db]">Cada contacto cuenta</p>
                    <h2 class="mt-4 text-4xl font-bold leading-tight xl:text-5xl">
                        El siguiente paso de cada oportunidad.
                    </h2>
                    <p class="mt-6 max-w-sm text-base leading-relaxed text-white/70">
                        Tu cartera, las conversaciones y el seguimiento de tus clientes, en un mismo lugar.
                    </p>
                    <span class="mt-8 inline-flex rounded-full bg-white/10 px-5 py-2 text-sm font-medium text-white/90 ring-1 ring-white/10">
                        Contactos · Seguimiento · Resultados
                    </span>
                </div>

                {{-- Footer --}}
                <p class="relative z-10 text-sm text-white/50">Sistema comercial AQUAKITA</p>
            </div>

            {{-- Form area --}}
            <div class="flex min-h-screen items-center justify-center bg-gray-50 p-6 sm:p-10 lg:min-h-0">
                <div class="w-full max-w-md">
                    {{-- Mobile logo --}}
                    <a href="/" wire:navigate class="mb-8 inline-block lg:hidden">
                        <img src="{{ route('brand.logo') }}" alt="Aquakita" class="h-16 w-auto">
                    </a>

                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
