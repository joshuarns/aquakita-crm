<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ auth()->user()?->company?->name ?? config('app.name', 'Laravel') }}</title>

        {{-- Evita el parpadeo de tema: aplica el modo guardado antes de pintar --}}
        <script>
            (function () {
                try {
                    var t = localStorage.getItem('theme');
                    if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    }
                } catch (e) {}
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full font-sans antialiased">
        <div x-data="{ sidebarOpen: false }" class="min-h-full">
            {{-- Overlay móvil --}}
            <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"
                 class="fixed inset-0 z-30 bg-slate-900/60 backdrop-blur-sm lg:hidden" x-cloak></div>

            {{-- Sidebar --}}
            <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                   class="fixed inset-y-0 left-0 z-40 w-64 bg-slate-900 dark:bg-slate-950 dark:border-r dark:border-slate-800 transition-transform duration-200 ease-out">
                <livewire:layout.navigation />
            </aside>

            {{-- Contenedor principal --}}
            <div class="lg:pl-64">
                {{-- Topbar (sobre todo móvil) --}}
                <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-slate-200 bg-white/80 px-4 backdrop-blur dark:border-slate-800 dark:bg-slate-950/80 lg:hidden">
                    <button @click="sidebarOpen = true" class="btn-ghost -ml-2 p-2" aria-label="Abrir menú">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <img src="{{ auth()->user()?->company?->logoUrl() ?? route('brand.logo') }}" alt="" class="h-8 w-auto">
                    <span class="font-semibold text-slate-800 dark:text-white">{{ auth()->user()?->company?->name ?? config('app.name') }}</span>
                </header>

                <main class="animate-fade-in">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
