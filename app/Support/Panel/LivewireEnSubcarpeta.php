<?php

namespace App\Support\Panel;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;

/**
 * El panel también anda en una subcarpeta (P13; en XAMPP, /pastura/public).
 * Livewire escribe la dirección de su script y la de sus pedidos desde la
 * raíz del dominio (`/livewire/...`) y no tiene una opción para cambiarlo;
 * acá se les pone la subcarpeta adelante en la página que sale. En la raíz
 * del dominio no hace nada.
 *
 * Escucha después de Livewire, que agrega esas etiquetas al terminar el
 * pedido: los proveedores de la app arrancan después que los de los paquetes.
 */
class LivewireEnSubcarpeta
{
    public static function registrar(): void
    {
        Event::listen(RequestHandled::class, function (RequestHandled $evento) {
            $base = rtrim($evento->request->getBaseUrl(), '/');
            $respuesta = $evento->response;

            if ($base === '' || ! str_contains((string) $respuesta->headers->get('content-type'), 'text/html')) {
                return;
            }

            $html = $respuesta->getContent();
            if (! is_string($html) || ! str_contains($html, '/livewire/')) {
                return;
            }

            $original = $respuesta->original ?? null;
            $respuesta->setContent(self::corregir($html, $base));
            $respuesta->original = $original;
        });
    }

    public static function corregir(string $html, string $base): string
    {
        return strtr($html, [
            '<script src="/livewire/' => '<script src="'.$base.'/livewire/',
            'data-update-uri="/livewire/' => 'data-update-uri="'.$base.'/livewire/',
        ]);
    }
}
