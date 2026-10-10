<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

/*
 * Nueva contraseña desde el enlace del mail (D52). Volt de Breeze con la
 * vista del sistema; al terminar redirige al login con el aviso en un flash
 * `success`, que <x-ui.toasts /> convierte en toast (regla 3).
 */
new #[Layout('layouts.guest')] class extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('success', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div>
    <h1 class="text-lg font-semibold text-gray-800">{{ __('acceso.restablecer.titulo') }}</h1>
    <p class="mt-1 text-sm text-gray-500">{{ __('acceso.restablecer.subtitulo') }}</p>

    <form wire:submit="resetPassword" class="mt-6 space-y-4">
        <x-ui.campo :etiqueta="__('acceso.login.email')" nombre="email">
            <x-ui.input wire:model="email" nombre="email" type="email" required autofocus autocomplete="username" />
        </x-ui.campo>

        <x-ui.campo :etiqueta="__('acceso.restablecer.password')" nombre="password">
            <x-ui.input wire:model="password" nombre="password" type="password" required autocomplete="new-password" />
        </x-ui.campo>

        <x-ui.campo :etiqueta="__('acceso.restablecer.confirmacion')" nombre="password_confirmation">
            <x-ui.input wire:model="password_confirmation" nombre="password_confirmation" type="password" required autocomplete="new-password" />
        </x-ui.campo>

        <x-ui.boton type="submit" icono="fa-key" class="w-full" wire:loading.attr="disabled" wire:target="resetPassword">
            <span wire:loading.remove wire:target="resetPassword">{{ __('acceso.restablecer.boton') }}</span>
            <span wire:loading wire:target="resetPassword">{{ __('acceso.restablecer.guardando') }}</span>
        </x-ui.boton>
    </form>
</div>
