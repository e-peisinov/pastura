<?php

use App\Livewire\Traits\EnviaToasts;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/*
 * Olvido de contraseña (D52). Volt de Breeze con la vista del sistema; el
 * aviso de "enlace enviado" sale por toast (regla 3).
 */
new #[Layout('layouts.guest')] class extends Component
{
    use EnviaToasts;

    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        $this->toastExito(__($status));
    }
}; ?>

<div>
    <h1 class="text-lg font-semibold text-gray-800">{{ __('acceso.olvido.titulo') }}</h1>
    <p class="mt-1 text-sm text-gray-500">{{ __('acceso.olvido.subtitulo') }}</p>

    <form wire:submit="sendPasswordResetLink" class="mt-6 space-y-4">
        <x-ui.campo :etiqueta="__('acceso.login.email')" nombre="email">
            <x-ui.input wire:model="email" nombre="email" type="email" required autofocus autocomplete="username" />
        </x-ui.campo>

        <x-ui.boton type="submit" icono="fa-paper-plane" class="w-full" wire:loading.attr="disabled" wire:target="sendPasswordResetLink">
            <span wire:loading.remove wire:target="sendPasswordResetLink">{{ __('acceso.olvido.boton') }}</span>
            <span wire:loading wire:target="sendPasswordResetLink">{{ __('acceso.olvido.enviando') }}</span>
        </x-ui.boton>

        <div class="text-center">
            <x-ui.boton variante="enlace" :href="route('login')" icono="fa-arrow-left" class="text-sm">
                {{ __('acceso.olvido.volver') }}
            </x-ui.boton>
        </div>
    </form>
</div>
