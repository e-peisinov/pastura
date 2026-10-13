<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Ir al panel desde la app sin volver a escribir la contraseña (P12). La app
 * pide con su token (`POST /api/v1/enlace-panel`) un enlace firmado que vence en un minuto; el pase que
 * lleva se guarda en la caché y se consume al usarlo, así sirve una sola vez.
 *
 * Por omisión lleva al tablero; la ayuda de la app pide `destino` para abrir
 * el instructivo (P25). Solo destinos de esta lista: va dentro de la firma.
 */
class PaseAlPanelController extends Controller
{
    private const VIGENCIA_SEGUNDOS = 60;

    /** destino => nombre de la ruta. */
    private const DESTINOS = [
        'instrucciones' => 'instrucciones',
    ];

    public function crear(Request $request): JsonResponse
    {
        $destino = $request->validate([
            'destino' => ['nullable', 'string', Rule::in(array_keys(self::DESTINOS))],
        ])['destino'] ?? null;

        $pase = Str::random(64);
        Cache::put($this->clave($pase), $request->user()->id, self::VIGENCIA_SEGUNDOS);

        $url = URL::temporarySignedRoute(
            'entrar-desde-app',
            now()->addSeconds(self::VIGENCIA_SEGUNDOS),
            array_filter(['pase' => $pase, 'destino' => $destino]),
        );

        return response()->json(['url' => $url]);
    }

    public function entrar(Request $request, string $pase): RedirectResponse
    {
        // Con la firma buena el pase se consume, entre o no: sirve una sola vez.
        $usuarioId = $request->hasValidSignature() ? Cache::pull($this->clave($pase)) : null;

        if ($usuarioId === null) {
            return redirect()->route('login')->with('error', __('api.enlace_vencido'));
        }

        $usuario = User::find($usuarioId);

        if ($usuario === null || ! $usuario->activo) {
            return redirect()->route('login')->with('error', __('auth.inactivo'));
        }

        // Si en este navegador había otra sesión abierta, queda la del dueño del pase.
        Auth::guard('web')->login($usuario);
        $request->session()->regenerate();

        return redirect()->route(self::DESTINOS[$request->string('destino')->toString()] ?? 'dashboard');
    }

    private function clave(string $pase): string
    {
        return 'pase-panel:'.hash('sha256', $pase);
    }
}
