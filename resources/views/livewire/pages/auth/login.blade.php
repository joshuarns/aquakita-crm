<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Quick sign-in for the seeded demo profiles (local environment only).
     */
    public function loginAs(string $email): void
    {
        abort_unless(app()->environment('local'), 403);

        $this->form->email = $email;
        $this->form->password = 'password';

        $this->login();
    }
}; ?>

<div>
    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#0891b2]">Bienvenido</p>
    <h1 class="mt-1 text-3xl font-bold text-gray-900">Inicia sesión</h1>
    <p class="mt-1 text-gray-500">Accede con el correo de tu cuenta.</p>

    <!-- Session Status -->
    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form wire:submit="login" class="mt-8 space-y-5">
        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Correo electrónico</label>
            <input wire:model="form.email" id="email" type="email" name="email" required autofocus autocomplete="username"
                   class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm transition focus:border-[#0891b2] focus:ring-[#0891b2]" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
            <input wire:model="form.password" id="password" type="password" name="password" required autocomplete="current-password"
                   class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm transition focus:border-[#0891b2] focus:ring-[#0891b2]" />
            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <label for="remember" class="inline-flex items-center">
            <input wire:model="form.remember" id="remember" type="checkbox" name="remember"
                   class="rounded border-gray-300 text-[#0891b2] shadow-sm focus:ring-[#0891b2]">
            <span class="ms-2 text-sm text-gray-600">Mantener sesión iniciada</span>
        </label>

        <button type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-[#0e7490] px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#155e75] focus:outline-none focus:ring-2 focus:ring-[#0891b2] focus:ring-offset-2">
            <span wire:loading.remove wire:target="login">Entrar al sistema</span>
            <span wire:loading wire:target="login">Entrando…</span>
            <svg wire:loading.remove wire:target="login" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h9.19L9.72 6.03a.75.75 0 1 1 1.06-1.06l4.5 4.5a.75.75 0 0 1 0 1.06l-4.5 4.5a.75.75 0 1 1-1.06-1.06l3.22-3.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
            </svg>
        </button>

        @if (Route::has('password.request'))
            <div class="text-center">
                <a class="text-sm font-medium text-[#0891b2] hover:text-[#155e75]" href="{{ route('password.request') }}" wire:navigate>
                    Olvidé mi contraseña
                </a>
            </div>
        @endif
    </form>

    @if (app()->environment('local'))
        <div class="mt-8 rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-sm font-semibold text-gray-900">Demostración local</p>
            <p class="text-xs text-gray-500">Selecciona un perfil. Los datos son ficticios.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ([
                    'Administrador' => 'admin@aquakita.test',
                    'Vendedor' => 'vendedor@aquakita.test',
                    'Supervisor' => 'supervisor@aquakita.test',
                    'Capturista' => 'capturista@aquakita.test',
                ] as $label => $email)
                    <button type="button" wire:click="loginAs('{{ $email }}')"
                            class="rounded-lg border border-[#0891b2]/40 px-3 py-1.5 text-sm font-medium text-[#0e7490] transition hover:bg-[#0891b2]/10">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</div>
