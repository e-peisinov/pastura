<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/*
 * Pantalla de ingreso (D52). Volt porque vino con Breeze (regla 6): se
 * cambió la vista, no el componente. Los mensajes van por toast (regla 3).
 */
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

        $this->redirectIntended(default: route('dashboard'), navigate: true);
    }
}; ?>

<div>
    <h1 class="text-lg font-semibold text-gray-800">{{ __('acceso.login.titulo') }}</h1>
    <p class="mt-1 text-sm text-gray-500">{{ __('acceso.login.subtitulo') }}</p>

    <form wire:submit="login" class="mt-6 space-y-4">
        <x-ui.campo :etiqueta="__('acceso.login.email')" nombre="form.email">
            <x-ui.input wire:model="form.email" nombre="form.email" type="email" required autofocus autocomplete="username" />
        </x-ui.campo>

        <x-ui.campo :etiqueta="__('acceso.login.password')" nombre="form.password">
            <x-ui.input wire:model="form.password" nombre="form.password" type="password" required autocomplete="current-password" />
        </x-ui.campo>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <x-ui.checkbox wire:model="form.remember" nombre="form.remember" :etiqueta="__('acceso.login.recordarme')" />

            @if (Route::has('password.request'))
                <x-ui.boton variante="enlace" :href="route('password.request')" class="text-sm">
                    {{ __('acceso.login.olvido') }}
                </x-ui.boton>
            @endif
        </div>

        <x-ui.boton type="submit" icono="fa-right-to-bracket" class="w-full" wire:loading.attr="disabled" wire:target="login">
            <span wire:loading.remove wire:target="login">{{ __('acceso.login.boton') }}</span>
            <span wire:loading wire:target="login">{{ __('acceso.login.ingresando') }}</span>
        </x-ui.boton>
    </form>
</div>
